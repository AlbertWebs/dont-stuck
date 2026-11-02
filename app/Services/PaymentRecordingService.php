<?php

namespace App\Services;

use App\Models\ApplicationStatusHistory;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use App\Models\LoanCharge;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentRecordingService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly PenaltyAccrualService $penalties) {}

    /**
     * @param  array{amount: string, paid_on: string, method: string, external_reference?: ?string, notes?: ?string}  $data
     */
    public function record(LoanAccount $account, User $actor, array $data, Request $request): Payment
    {
        return DB::transaction(function () use ($account, $actor, $data, $request): Payment {
            $lockedAccount = LoanAccount::query()->lockForUpdate()->findOrFail($account->id);

            if (! in_array($lockedAccount->status, ['active', 'partially_repaid', 'overdue', 'defaulted'], true)) {
                throw ValidationException::withMessages(['account' => 'This loan account is not accepting repayments.']);
            }

            $paidOn = CarbonImmutable::parse($data['paid_on'])->startOfDay();
            $disbursedOn = CarbonImmutable::parse((string) $lockedAccount->disbursed_at)->startOfDay();

            if ($paidOn->lessThan($disbursedOn)) {
                throw ValidationException::withMessages(['paid_on' => 'A payment cannot be recorded before the disbursement date.']);
            }

            $schedules = RepaymentSchedule::query()
                ->where('loan_account_id', $lockedAccount->id)
                ->orderBy('due_date')
                ->orderBy('installment_number')
                ->lockForUpdate()
                ->get();

            foreach ($schedules as $schedule) {
                $this->penalties->accrueThrough($lockedAccount, $schedule, $paidOn, $actor);
            }

            $amountCents = Money::toCents($data['amount']);
            $totalOutstandingCents = $schedules->sum(fn (RepaymentSchedule $schedule): int => $this->totalOutstandingCents($schedule));

            if ($amountCents > $totalOutstandingCents) {
                throw ValidationException::withMessages(['amount' => 'The amount received exceeds the current scheduled balance. Record a separate adjustment for any excess.']);
            }

            if ($amountCents === 0 || $totalOutstandingCents === 0) {
                throw ValidationException::withMessages(['amount' => 'There is no outstanding scheduled balance on this loan account.']);
            }

            $payment = Payment::create([
                'loan_account_id' => $lockedAccount->id,
                'amount' => Money::fromCents($amountCents),
                'paid_on' => $paidOn->toDateString(),
                'method' => $data['method'],
                'external_reference' => $data['external_reference'] ?? null,
                'recorded_by' => $actor->id,
                'status' => 'posted',
                'notes' => $data['notes'] ?? null,
            ]);

            $remainingCents = $amountCents;
            $allocationOrder = $this->allocationOrder($lockedAccount->allocation_order);

            foreach ($schedules as $schedule) {
                $schedule->refresh();
                $components = [
                    'penalties' => max(0, Money::toCents((string) $schedule->penalties_due) - Money::toCents((string) $schedule->penalties_paid)),
                    'fees' => max(0, Money::toCents((string) $schedule->fees_due) - Money::toCents((string) $schedule->fees_paid)),
                    'interest' => max(0, Money::toCents((string) $schedule->interest_due) - Money::toCents((string) $schedule->interest_paid)),
                    'principal' => max(0, Money::toCents((string) $schedule->principal_due) - Money::toCents((string) $schedule->principal_paid)),
                ];
                $allocated = ['penalties' => 0, 'fees' => 0, 'interest' => 0, 'principal' => 0];

                foreach ($allocationOrder as $component) {
                    if ($remainingCents === 0) {
                        break;
                    }

                    $applied = min($remainingCents, $components[$component]);
                    $allocated[$component] = $applied;
                    $remainingCents -= $applied;
                }

                $allocatedCents = array_sum($allocated);

                if ($allocatedCents === 0) {
                    continue;
                }

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'repayment_schedule_id' => $schedule->id,
                    'principal_amount' => Money::fromCents($allocated['principal']),
                    'interest_amount' => Money::fromCents($allocated['interest']),
                    'fees_amount' => Money::fromCents($allocated['fees']),
                    'penalty_amount' => Money::fromCents($allocated['penalties']),
                ]);

                $schedule->update([
                    'principal_paid' => Money::fromCents(Money::toCents((string) $schedule->principal_paid) + $allocated['principal']),
                    'interest_paid' => Money::fromCents(Money::toCents((string) $schedule->interest_paid) + $allocated['interest']),
                    'fees_paid' => Money::fromCents(Money::toCents((string) $schedule->fees_paid) + $allocated['fees']),
                    'penalties_paid' => Money::fromCents(Money::toCents((string) $schedule->penalties_paid) + $allocated['penalties']),
                ]);

                $this->applyChargePayments($lockedAccount, $schedule, $allocated['fees'], $allocated['penalties']);
                $this->updateScheduleStatus($schedule->fresh());
            }

            if ($remainingCents !== 0) {
                throw ValidationException::withMessages(['amount' => 'The payment could not be fully allocated to the configured schedule.']);
            }

            $this->refreshAccountStatus($lockedAccount, $actor);
            $this->auditLogger->record($actor, 'repayment.recorded', $payment, [
                'amount' => Money::fromCents($amountCents),
                'method' => $data['method'],
                'external_reference' => $data['external_reference'] ?? null,
            ], $request);

            return $payment->fresh(['allocations.schedule', 'loanAccount']);
        });
    }

    public function reverse(Payment $payment, User $actor, string $reason, Request $request): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $reason, $request): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->with('allocations')->findOrFail($payment->id);

            if ($lockedPayment->status !== 'posted') {
                throw ValidationException::withMessages(['payment' => 'Only a posted payment can be reversed.']);
            }

            $account = LoanAccount::query()->lockForUpdate()->findOrFail($lockedPayment->loan_account_id);

            foreach ($lockedPayment->allocations as $allocation) {
                $schedule = RepaymentSchedule::query()->lockForUpdate()->findOrFail($allocation->repayment_schedule_id);
                $schedule->update([
                    'principal_paid' => Money::fromCents(Money::toCents((string) $schedule->principal_paid) - Money::toCents((string) $allocation->principal_amount)),
                    'interest_paid' => Money::fromCents(Money::toCents((string) $schedule->interest_paid) - Money::toCents((string) $allocation->interest_amount)),
                    'fees_paid' => Money::fromCents(Money::toCents((string) $schedule->fees_paid) - Money::toCents((string) $allocation->fees_amount)),
                    'penalties_paid' => Money::fromCents(Money::toCents((string) $schedule->penalties_paid) - Money::toCents((string) $allocation->penalty_amount)),
                ]);
                $this->reverseChargePayments($account, $schedule, Money::toCents((string) $allocation->fees_amount), Money::toCents((string) $allocation->penalty_amount));
                $this->updateScheduleStatus($schedule->fresh());
            }

            $lockedPayment->update([
                'status' => 'reversed',
                'reversed_by' => $actor->id,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ]);

            $this->refreshAccountStatus($account, $actor);
            $this->auditLogger->record($actor, 'repayment.reversed', $lockedPayment, [
                'reason' => $reason,
                'amount' => (string) $lockedPayment->amount,
            ], $request);

            return $lockedPayment->fresh(['allocations.schedule', 'loanAccount']);
        });
    }

    /** @return array<int, string> */
    private function allocationOrder(?string $order): array
    {
        return match ($order) {
            'fees_penalties_interest_principal' => ['fees', 'penalties', 'interest', 'principal'],
            'principal_interest_fees_penalties' => ['principal', 'interest', 'fees', 'penalties'],
            default => ['penalties', 'fees', 'interest', 'principal'],
        };
    }

    private function totalOutstandingCents(RepaymentSchedule $schedule): int
    {
        return $this->baseOutstandingCents($schedule)
            + max(0, Money::toCents((string) $schedule->penalties_due) - Money::toCents((string) $schedule->penalties_paid));
    }

    private function baseOutstandingCents(RepaymentSchedule $schedule): int
    {
        return max(0, Money::toCents((string) $schedule->principal_due) - Money::toCents((string) $schedule->principal_paid))
            + max(0, Money::toCents((string) $schedule->interest_due) - Money::toCents((string) $schedule->interest_paid))
            + max(0, Money::toCents((string) $schedule->fees_due) - Money::toCents((string) $schedule->fees_paid));
    }

    private function updateScheduleStatus(RepaymentSchedule $schedule): void
    {
        $outstanding = $this->totalOutstandingCents($schedule);
        $paid = Money::toCents((string) $schedule->principal_paid)
            + Money::toCents((string) $schedule->interest_paid)
            + Money::toCents((string) $schedule->fees_paid)
            + Money::toCents((string) $schedule->penalties_paid);

        $status = $outstanding === 0
            ? 'paid'
            : ($schedule->due_date->lt(today()) ? 'overdue' : ($paid > 0 ? 'partial' : 'pending'));
        $schedule->update(['status' => $status]);
    }

    private function applyChargePayments(LoanAccount $account, RepaymentSchedule $schedule, int $feesCents, int $penaltiesCents): void
    {
        $this->updateCharges($account, $schedule, ['processing_fee', 'commissioning_fee'], $feesCents, false);
        $this->updateCharges($account, $schedule, ['late_penalty'], $penaltiesCents, false);
    }

    private function reverseChargePayments(LoanAccount $account, RepaymentSchedule $schedule, int $feesCents, int $penaltiesCents): void
    {
        $this->updateCharges($account, $schedule, ['processing_fee', 'commissioning_fee'], $feesCents, true);
        $this->updateCharges($account, $schedule, ['late_penalty'], $penaltiesCents, true);
    }

    /** @param array<int, string> $chargeTypes */
    private function updateCharges(LoanAccount $account, RepaymentSchedule $schedule, array $chargeTypes, int $amountCents, bool $reverse): void
    {
        if ($amountCents === 0) {
            return;
        }

        $charges = LoanCharge::query()
            ->where('loan_account_id', $account->id)
            ->where('repayment_schedule_id', $schedule->id)
            ->whereIn('charge_type', $chargeTypes)
            ->when($reverse, fn ($query) => $query->orderByDesc('id'), fn ($query) => $query->orderBy('id'))
            ->lockForUpdate()
            ->get();

        $remaining = $amountCents;

        foreach ($charges as $charge) {
            if ($remaining === 0) {
                break;
            }

            $paidCents = Money::toCents((string) $charge->amount_paid);
            $totalCents = Money::toCents((string) $charge->amount);
            $available = $reverse ? $paidCents : $totalCents - $paidCents;
            $adjustment = min($remaining, $available);
            $newPaid = $reverse ? $paidCents - $adjustment : $paidCents + $adjustment;
            $status = $newPaid === $totalCents ? 'paid' : ($newPaid > 0 ? 'partial' : 'due');

            $charge->update([
                'amount_paid' => Money::fromCents($newPaid),
                'status' => $status,
                'paid_at' => $status === 'paid' ? ($charge->paid_at ?? now()) : null,
            ]);
            $remaining -= $adjustment;
        }
    }

    private function refreshAccountStatus(LoanAccount $account, User $actor): void
    {
        $schedules = $account->schedules()->orderBy('due_date')->get();
        $fullyPaid = $schedules->isNotEmpty() && $schedules->every(fn (RepaymentSchedule $schedule): bool => $this->totalOutstandingCents($schedule) === 0);
        $overdue = $schedules->filter(fn (RepaymentSchedule $schedule): bool => $schedule->due_date->isBefore(today()) && $this->totalOutstandingCents($schedule) > 0);

        if ($fullyPaid) {
            $newStatus = 'fully_repaid';
        } elseif ($overdue->isNotEmpty()) {
            $daysPastDue = (int) $overdue->min('due_date')->diffInDays(today());
            $newStatus = $account->default_threshold_days !== null && $daysPastDue >= $account->default_threshold_days ? 'defaulted' : 'overdue';
        } elseif ($account->payments()->where('status', 'posted')->exists()) {
            $newStatus = 'partially_repaid';
        } else {
            $newStatus = 'active';
        }

        $oldStatus = $account->status;
        $account->update(['status' => $newStatus]);

        if ($newStatus === 'fully_repaid' && $account->application?->status !== 'fully_repaid') {
            $application = LoanApplication::query()->lockForUpdate()->findOrFail($account->loan_application_id);
            $previousApplicationStatus = $application->status;
            $application->update(['status' => 'fully_repaid']);
            ApplicationStatusHistory::create([
                'loan_application_id' => $application->id,
                'from_status' => $previousApplicationStatus,
                'to_status' => 'fully_repaid',
                'actor_id' => $actor->id,
                'remarks' => 'All scheduled principal, interest and charges have been paid.',
            ]);
        } elseif ($oldStatus === 'fully_repaid' && $newStatus !== 'fully_repaid') {
            $application = LoanApplication::query()->lockForUpdate()->findOrFail($account->loan_application_id);
            $application->update(['status' => 'active']);
            ApplicationStatusHistory::create([
                'loan_application_id' => $application->id,
                'from_status' => 'fully_repaid',
                'to_status' => 'active',
                'actor_id' => $actor->id,
                'remarks' => 'A previously posted repayment was reversed; the balance is open again.',
            ]);
        }
    }
}
