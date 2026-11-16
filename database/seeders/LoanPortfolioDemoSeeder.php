<?php

namespace Database\Seeders;

use App\Models\Disbursement;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RepaymentSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoanPortfolioDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<int, string> */
    private const CLIENT_NAMES = [
        'Victor Korir', 'Madelyne Ondiso', 'Albert Muhatia', 'John Kirimi', 'Allan Mukwana',
        'James Kimani', 'Wanjiku Njeri', 'Brian Ochieng', 'Faith Chebet', 'Dennis Mwangi',
        'Grace Wambui', 'Peter Otieno', 'Mercy Atieno', 'Samuel Kiptoo', 'Lydia Nyambura',
        'Daniel Kiplagat', 'Esther Wairimu', 'Kevin Mutua', 'Purity Jepkosgei', 'Joseph Maina',
        'Caroline Achieng', 'Michael Barasa', 'Ruth Wanjala', 'George Kamau', 'Irene Muthoni',
        'Patrick Njoroge', 'Cynthia Adhiambo', 'Francis Muli', 'Beatrice Chepkemoi', 'Emmanuel Odhiambo',
    ];

    /** @var array<int, string> */
    private const PIPELINE_STATUSES = [
        'submitted', 'under_review', 'verification_assessment', 'additional_information_required',
        'pending_approval', 'on_hold', 'approved', 'finance_processing', 'pending_disbursement',
    ];

    /** @var array<int, string> */
    private const LOAN_STATUSES = [
        'active', 'active', 'active', 'active', 'active', 'active',
        'partially_repaid', 'partially_repaid', 'partially_repaid', 'partially_repaid',
        'overdue', 'overdue', 'overdue', 'overdue',
        'defaulted', 'defaulted',
        'fully_repaid', 'fully_repaid', 'fully_repaid', 'fully_repaid',
        'active',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $product = LoanProduct::query()->where('slug', 'employed-loan')->firstOrFail();
        $officer = User::query()->firstOrCreate(
            ['email' => 'demo.loanofficer@example.test'],
            [
                'name' => 'Demo Loan Officer',
                'password' => Hash::make(Str::random(48)),
                'role' => 'loan_officer',
                'is_active' => true,
            ],
        );

        foreach (self::CLIENT_NAMES as $offset => $fullName) {
            $sequence = $offset + 1;
            $email = Str::slug($fullName, '.').'.demo@example.test';
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $fullName,
                    'password' => Hash::make(Str::random(48)),
                    'role' => 'client',
                    'is_active' => true,
                ],
            );

            $borrower = $user->borrower()->updateOrCreate([], [
                'full_name' => $fullName,
                'national_id' => (string) (29000000 + $sequence),
                'phone' => sprintf('+2547%08d', 10000000 + $sequence),
                'email' => $email,
                'residence' => ['Nairobi', 'Kisumu', 'Nakuru', 'Eldoret', 'Mombasa'][$offset % 5],
                'employer' => ['Kenya Health Services', 'Lakeview Traders', 'Rift Valley Supplies', 'Eastern Logistics', 'Greenfields Academy'][$offset % 5],
                'employer_location' => ['Nairobi', 'Kisumu', 'Nakuru', 'Eldoret', 'Mombasa'][$offset % 5],
                'job_title' => ['Accountant', 'Teacher', 'Clinical Officer', 'Sales Manager', 'Operations Assistant'][$offset % 5],
                'net_salary' => (string) (35000 + (($offset % 8) * 12500)).'.00',
                'next_of_kin_name' => 'Family Contact '.($sequence),
                'next_of_kin_id' => (string) (28000000 + $sequence),
                'next_of_kin_relation' => ['Sibling', 'Parent', 'Spouse', 'Cousin'][$offset % 4],
                'next_of_kin_phone' => sprintf('+2547%08d', 20000000 + $sequence),
            ]);

            $applicationStatus = self::PIPELINE_STATUSES[$offset] ?? self::LOAN_STATUSES[$offset - count(self::PIPELINE_STATUSES)];
            $loanStatus = $offset < count(self::PIPELINE_STATUSES) ? null : $applicationStatus;
            $requestedAmount = 20000 + ($sequence * 5000);
            $applicationDate = $this->applicationDate($loanStatus, $sequence);
            $application = $borrower->applications()->updateOrCreate(
                ['reference' => sprintf('DSS-DEMO-APP-%04d', $sequence)],
                [
                    'loan_product_id' => $product->id,
                    'assigned_user_id' => $officer->id,
                    'requested_amount' => number_format($requestedAmount, 2, '.', ''),
                    'requested_months' => 6,
                    'approved_amount' => in_array($applicationStatus, ['approved', 'finance_processing', 'pending_disbursement', 'active', 'partially_repaid', 'overdue', 'defaulted', 'fully_repaid'], true)
                        ? number_format($requestedAmount, 2, '.', '')
                        : null,
                    'approved_months' => in_array($applicationStatus, ['approved', 'finance_processing', 'pending_disbursement', 'active', 'partially_repaid', 'overdue', 'defaulted', 'fully_repaid'], true)
                        ? 6
                        : null,
                    'purpose' => ['School fees', 'Business stock', 'Medical expenses', 'Home improvement', 'Professional course'][$offset % 5],
                    'status' => $applicationStatus,
                    'consented_at' => $applicationDate,
                    'applicant_notes' => 'Demonstration portfolio record.',
                    'decision_notes' => in_array($applicationStatus, ['active', 'partially_repaid', 'overdue', 'defaulted', 'fully_repaid'], true)
                        ? 'Demonstration application approved and disbursed.'
                        : null,
                    'application_data' => [],
                ],
            );
            $this->setTimestamps($application, $applicationDate);

            if ($loanStatus !== null) {
                $this->seedLoan($application, $borrower->id, $loanStatus, $requestedAmount, $sequence, $officer);
            }
        }
    }

    private function seedLoan(LoanApplication $application, int $borrowerId, string $status, int $principal, int $sequence, User $officer): void
    {
        $disbursedOn = $this->disbursementDate($status, $sequence);
        $termMonths = 6;
        $maturityDate = $disbursedOn->addMonthsNoOverflow($termMonths);
        $account = LoanAccount::query()->updateOrCreate(
            ['loan_application_id' => $application->id],
            [
                'reference' => sprintf('DSS-DEMO-LN-%04d', $sequence),
                'borrower_id' => $borrowerId,
                'principal_amount' => number_format($principal, 2, '.', ''),
                'disbursed_amount' => number_format($principal, 2, '.', ''),
                'interest_rate' => null,
                'interest_basis' => null,
                'processing_fee' => null,
                'commissioning_fee' => null,
                'daily_late_fee' => null,
                'late_penalty_start' => null,
                'allocation_order' => null,
                'fee_collection_method' => null,
                'default_threshold_days' => null,
                'term_months' => $termMonths,
                'disbursed_at' => $disbursedOn->toDateString(),
                'maturity_date' => $maturityDate->toDateString(),
                'status' => $status,
            ],
        );
        $this->setTimestamps($account, $disbursedOn);

        Disbursement::query()->updateOrCreate(
            ['provider_reference' => sprintf('DEMO-DISB-%04d', $sequence)],
            [
                'loan_account_id' => $account->id,
                'amount' => number_format($principal, 2, '.', ''),
                'gross_amount' => number_format($principal, 2, '.', ''),
                'fees_withheld' => '0.00',
                'disbursed_on' => $disbursedOn->toDateString(),
                'method' => 'mpesa',
                'recorded_by' => $officer->id,
                'notes' => 'Demonstration disbursement record.',
            ],
        );

        $principalCents = $principal * 100;
        $basePrincipalCents = intdiv($principalCents, $termMonths);
        $paidInstallments = match ($status) {
            'partially_repaid' => 1,
            'overdue' => 2,
            'fully_repaid' => $termMonths,
            default => 0,
        };

        for ($installment = 1; $installment <= $termMonths; $installment++) {
            $installmentPrincipalCents = $installment === $termMonths
                ? $principalCents - ($basePrincipalCents * ($termMonths - 1))
                : $basePrincipalCents;
            $principalDue = number_format($installmentPrincipalCents / 100, 2, '.', '');
            $dueDate = $disbursedOn->addMonthsNoOverflow($installment);
            $isPaid = $installment <= $paidInstallments;
            $scheduleStatus = $isPaid ? 'paid' : ($dueDate->isBefore(today()) ? 'overdue' : 'pending');
            $schedule = RepaymentSchedule::query()->updateOrCreate(
                ['loan_account_id' => $account->id, 'installment_number' => $installment],
                [
                    'due_date' => $dueDate->toDateString(),
                    'principal_due' => $principalDue,
                    'interest_due' => '0.00',
                    'fees_due' => '0.00',
                    'principal_paid' => $isPaid ? $principalDue : '0.00',
                    'interest_paid' => '0.00',
                    'fees_paid' => '0.00',
                    'penalties_due' => '0.00',
                    'penalties_paid' => '0.00',
                    'status' => $scheduleStatus,
                ],
            );
            $this->setTimestamps($schedule, $dueDate);

            if ($isPaid) {
                $payment = Payment::query()->updateOrCreate(
                    ['external_reference' => sprintf('DEMO-PAY-%04d-%02d', $sequence, $installment)],
                    [
                        'loan_account_id' => $account->id,
                        'amount' => $principalDue,
                        'paid_on' => $dueDate->toDateString(),
                        'method' => 'mpesa',
                        'recorded_by' => $officer->id,
                        'status' => 'posted',
                        'notes' => 'Demonstration repayment record.',
                    ],
                );
                $this->setTimestamps($payment, $dueDate);
                PaymentAllocation::query()->updateOrCreate(
                    ['payment_id' => $payment->id, 'repayment_schedule_id' => $schedule->id],
                    [
                        'principal_amount' => $principalDue,
                        'interest_amount' => '0.00',
                        'fees_amount' => '0.00',
                        'penalty_amount' => '0.00',
                    ],
                );
            }
        }
    }

    private function applicationDate(?string $loanStatus, int $sequence): CarbonImmutable
    {
        $daysAgo = match ($loanStatus) {
            'active' => 30,
            'partially_repaid' => 75,
            'overdue' => 155,
            'defaulted' => 330,
            'fully_repaid' => 430,
            default => 20 + ($sequence * 9),
        };

        return CarbonImmutable::today()->subDays($daysAgo + ($sequence % 6));
    }

    private function disbursementDate(string $loanStatus, int $sequence): CarbonImmutable
    {
        $daysAgo = match ($loanStatus) {
            'active' => 14 + ($sequence % 7),
            'partially_repaid' => 55 + ($sequence % 10),
            'overdue' => 150 + ($sequence % 15),
            'defaulted' => 320 + ($sequence % 20),
            'fully_repaid' => 250 + ($sequence % 20),
            default => 60,
        };

        return CarbonImmutable::today()->subDays($daysAgo);
    }

    private function setTimestamps(Model $model, CarbonImmutable $timestamp): void
    {
        $model->timestamps = false;
        $model->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->saveQuietly();
        $model->timestamps = true;
    }
}
