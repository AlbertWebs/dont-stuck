<?php

namespace App\Services;

use App\Models\ApplicationStatusHistory;
use App\Models\Disbursement;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use App\Models\LoanCharge;
use App\Models\LoanProduct;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DisbursementService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{disbursed_on: string, first_repayment_due_on: string, method: string, provider_reference?: ?string, notes?: ?string}  $data
     */
    public function confirm(LoanApplication $application, User $actor, array $data, Request $request): LoanAccount
    {
        return DB::transaction(function () use ($application, $actor, $data, $request): LoanAccount {
            $lockedApplication = LoanApplication::query()->lockForUpdate()->findOrFail($application->id);

            if ($lockedApplication->status !== 'pending_disbursement') {
                throw ValidationException::withMessages(['application' => 'Only finance-processed applications awaiting disbursement can be paid out.']);
            }

            if ($lockedApplication->loanAccount()->exists()) {
                throw ValidationException::withMessages(['application' => 'A loan account already exists for this application.']);
            }

            if (! $lockedApplication->approved_amount || ! $lockedApplication->approved_months) {
                throw ValidationException::withMessages(['application' => 'Approved amount and repayment period must be recorded before disbursement.']);
            }

            if ($lockedApplication->approved_months < 1 || $lockedApplication->approved_months > 3) {
                throw ValidationException::withMessages(['application' => 'The approved repayment period must be between 1 and 3 months before disbursement.']);
            }

            $product = LoanProduct::query()->lockForUpdate()->find($lockedApplication->loan_product_id);

            if (! $product) {
                throw new ModelNotFoundException('Loan product was not found.');
            }

            $this->ensureProductTermsAreComplete($product);
            $principalCents = Money::toCents((string) $lockedApplication->approved_amount);
            $this->ensureAmountWithinProductLimits($product, $principalCents);

            if ($product->repayment_months !== null && $product->repayment_months !== $lockedApplication->approved_months) {
                throw ValidationException::withMessages(['application' => 'The approved repayment period must match the configured product term.']);
            }

            $processingFeeCents = Money::percentage($principalCents, (string) $product->processing_fee_rate);
            $commissioningFeeCents = Money::toCents((string) $product->commissioning_fee);
            $totalFeesCents = $processingFeeCents + $commissioningFeeCents;
            $feesWithheldCents = $product->fee_collection_method === 'deducted_from_disbursement' ? $totalFeesCents : 0;
            $netDisbursementCents = $principalCents - $feesWithheldCents;

            if ($netDisbursementCents <= 0) {
                throw ValidationException::withMessages(['application' => 'Configured fees leave no positive amount to disburse.']);
            }

            $disbursementDate = CarbonImmutable::parse($data['disbursed_on'])->startOfDay();
            $firstDueDate = CarbonImmutable::parse($data['first_repayment_due_on'])->startOfDay();

            if ($firstDueDate->lessThanOrEqualTo($disbursementDate)) {
                throw ValidationException::withMessages(['first_repayment_due_on' => 'The first installment must be due after the disbursement date.']);
            }

            $account = LoanAccount::create([
                'reference' => 'LN-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'loan_application_id' => $lockedApplication->id,
                'borrower_id' => $lockedApplication->borrower_id,
                'principal_amount' => Money::fromCents($principalCents),
                'disbursed_amount' => Money::fromCents($netDisbursementCents),
                'interest_rate' => $product->interest_rate,
                'interest_basis' => $product->interest_basis,
                'processing_fee' => Money::fromCents($processingFeeCents),
                'commissioning_fee' => Money::fromCents($commissioningFeeCents),
                'daily_late_fee' => $product->daily_late_fee,
                'late_penalty_start' => $product->late_penalty_start,
                'allocation_order' => $product->allocation_order,
                'fee_collection_method' => $product->fee_collection_method,
                'default_threshold_days' => $product->default_threshold_days,
                'term_months' => $lockedApplication->approved_months,
                'disbursed_at' => $disbursementDate->toDateString(),
                'status' => 'active',
            ]);

            $outstandingPrincipalCents = $principalCents;
            $baseInstallmentPrincipalCents = intdiv($principalCents, $lockedApplication->approved_months);
            $lastDueDate = $firstDueDate;

            for ($installment = 1; $installment <= $lockedApplication->approved_months; $installment++) {
                $installmentPrincipalCents = $installment === $lockedApplication->approved_months
                    ? $outstandingPrincipalCents
                    : $baseInstallmentPrincipalCents;
                $interestBaseCents = $product->interest_basis === 'flat_monthly_equal_principal'
                    ? $principalCents
                    : $outstandingPrincipalCents;
                $interestCents = Money::percentage($interestBaseCents, (string) $product->interest_rate);
                $feesDueCents = $installment === 1 && $product->fee_collection_method === 'first_installment'
                    ? $totalFeesCents
                    : 0;
                $dueDate = $firstDueDate->addMonthsNoOverflow($installment - 1);

                $schedule = RepaymentSchedule::create([
                    'loan_account_id' => $account->id,
                    'installment_number' => $installment,
                    'due_date' => $dueDate->toDateString(),
                    'principal_due' => Money::fromCents($installmentPrincipalCents),
                    'interest_due' => Money::fromCents($interestCents),
                    'fees_due' => Money::fromCents($feesDueCents),
                    'status' => 'pending',
                ]);

                if ($installment === 1) {
                    $this->recordFeeCharge($account, $schedule, 'processing_fee', $processingFeeCents, $disbursementDate, $actor, $feesWithheldCents > 0);
                    $this->recordFeeCharge($account, $schedule, 'commissioning_fee', $commissioningFeeCents, $disbursementDate, $actor, $feesWithheldCents > 0);
                }

                $outstandingPrincipalCents -= $installmentPrincipalCents;
                $lastDueDate = $dueDate;
            }

            $account->update(['maturity_date' => $lastDueDate->toDateString()]);

            Disbursement::create([
                'loan_account_id' => $account->id,
                'amount' => Money::fromCents($netDisbursementCents),
                'gross_amount' => Money::fromCents($principalCents),
                'fees_withheld' => Money::fromCents($feesWithheldCents),
                'disbursed_on' => $disbursementDate->toDateString(),
                'method' => $data['method'],
                'provider_reference' => $data['provider_reference'] ?? null,
                'recorded_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $fromStatus = $lockedApplication->status;
            $lockedApplication->update(['status' => 'disbursed']);
            ApplicationStatusHistory::create([
                'loan_application_id' => $lockedApplication->id,
                'from_status' => $fromStatus,
                'to_status' => 'disbursed',
                'actor_id' => $actor->id,
                'remarks' => 'Manual disbursement confirmation recorded.',
            ]);
            $lockedApplication->update(['status' => 'active']);
            ApplicationStatusHistory::create([
                'loan_application_id' => $lockedApplication->id,
                'from_status' => 'disbursed',
                'to_status' => 'active',
                'actor_id' => $actor->id,
                'remarks' => 'Repayment monitoring has started.',
            ]);

            $this->auditLogger->record($actor, 'loan.disbursement_confirmed', $account, [
                'gross_amount' => Money::fromCents($principalCents),
                'net_amount' => Money::fromCents($netDisbursementCents),
                'fees_withheld' => Money::fromCents($feesWithheldCents),
                'method' => $data['method'],
            ], $request);

            return $account->fresh(['application', 'schedules', 'disbursements']);
        });
    }

    private function ensureProductTermsAreComplete(LoanProduct $product): void
    {
        $requiredFields = [
            'interest_rate',
            'interest_basis',
            'processing_fee_rate',
            'commissioning_fee',
            'daily_late_fee',
            'late_penalty_start',
            'allocation_order',
            'fee_collection_method',
        ];
        $missingFields = array_filter($requiredFields, fn (string $field): bool => $product->{$field} === null);

        if ($missingFields !== []) {
            throw ValidationException::withMessages(['application' => 'Configure the product interest, fee, penalty and allocation rules before disbursement.']);
        }
    }

    private function ensureAmountWithinProductLimits(LoanProduct $product, int $amountCents): void
    {
        if ($product->minimum_amount !== null && $amountCents < Money::toCents((string) $product->minimum_amount)) {
            throw ValidationException::withMessages(['application' => 'The approved amount is below the configured product minimum.']);
        }

        if ($product->maximum_amount !== null && $amountCents > Money::toCents((string) $product->maximum_amount)) {
            throw ValidationException::withMessages(['application' => 'The approved amount is above the configured product maximum.']);
        }
    }

    private function recordFeeCharge(LoanAccount $account, RepaymentSchedule $schedule, string $chargeType, int $amountCents, CarbonImmutable $chargedOn, User $actor, bool $withheld): void
    {
        if ($amountCents === 0) {
            return;
        }

        LoanCharge::create([
            'loan_account_id' => $account->id,
            'repayment_schedule_id' => $withheld ? null : $schedule->id,
            'charge_type' => $chargeType,
            'amount' => Money::fromCents($amountCents),
            'amount_paid' => $withheld ? Money::fromCents($amountCents) : '0.00',
            'charged_on' => $chargedOn->toDateString(),
            'status' => $withheld ? 'withheld' : 'due',
            'paid_at' => $withheld ? now() : null,
            'recorded_by' => $actor->id,
            'notes' => $withheld ? 'Deducted from the confirmed disbursement.' : 'Due with the first scheduled installment.',
        ]);
    }
}
