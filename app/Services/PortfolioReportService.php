<?php

namespace App\Services;

use App\Models\Borrower;
use App\Models\Disbursement;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class PortfolioReportService
{
    /** @var array<string, string> */
    private const TITLES = [
        'applications' => 'Loan applications',
        'disbursements' => 'Disbursement report',
        'repayments' => 'Repayment report',
        'outstanding' => 'Outstanding loans report',
        'overdue' => 'Overdue and default report',
        'borrower_statement' => 'Borrower statement summary',
    ];

    /** @return array<string, string> */
    public function titles(): array
    {
        return self::TITLES;
    }

    /** @return array<int, string> */
    public function headers(string $type): array
    {
        return match ($type) {
            'applications' => ['Application reference', 'Applicant', 'Product', 'Requested KES', 'Approved KES', 'Status', 'Loan officer', 'Submitted at'],
            'disbursements' => ['Loan reference', 'Borrower', 'Product', 'Gross principal KES', 'Net payout KES', 'Fees withheld KES', 'Date', 'Recorded by', 'Provider reference'],
            'repayments' => ['Receipt', 'Loan reference', 'Borrower', 'Amount KES', 'Principal KES', 'Interest KES', 'Fees KES', 'Penalty KES', 'Date', 'Method', 'External reference', 'Status', 'Recorded by'],
            'outstanding' => ['Loan reference', 'Borrower', 'Product', 'Principal open KES', 'Interest open KES', 'Fees open KES', 'Penalty open KES', 'Total open KES', 'Status', 'Maturity date'],
            'overdue' => ['Loan reference', 'Borrower', 'Due date', 'Installment', 'Days overdue', 'Principal open KES', 'Interest open KES', 'Fees open KES', 'Penalty open KES', 'Total overdue KES', 'Account status'],
            'borrower_statement' => ['Date', 'Loan reference', 'Transaction', 'Principal KES', 'Interest KES', 'Fees KES', 'Penalty KES', 'Amount KES', 'Current balance KES', 'Reference / notes', 'Status'],
            default => throw new \InvalidArgumentException('Unsupported report type.'),
        };
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function summary(User $user, array $filters): array
    {
        $apps = $this->applications($user, $filters);
        $accounts = $this->accounts($user, $filters);
        $payments = $this->payments($user, $filters)->where('payments.status', 'posted');
        $disbursements = $this->disbursements($user, $filters);
        $schedules = $this->schedules($user, $filters);
        $overdue = (clone $schedules)->whereDate('due_date', '<', today())->whereIn('status', ['pending', 'partial', 'overdue']);

        return [
            'application_count' => (clone $apps)->count(),
            'pending_applications' => (clone $apps)->whereIn('status', ['submitted', 'under_review', 'verification_assessment', 'pending_approval', 'additional_information_required', 'on_hold'])->count(),
            'approved_applications' => (clone $apps)->whereIn('status', ['approved', 'finance_processing', 'pending_disbursement', 'disbursed', 'active', 'fully_repaid'])->count(),
            'disbursement_total' => Money::fromCents($this->sumMoney($disbursements, 'amount')),
            'active_account_count' => (clone $accounts)->whereIn('loan_accounts.status', ['active', 'partially_repaid', 'overdue', 'defaulted'])->count(),
            'active_portfolio' => Money::fromCents($this->sumOutstanding(clone $schedules)),
            'repayments_total' => Money::fromCents($this->sumMoney(clone $payments, 'amount')),
            'outstanding_total' => Money::fromCents($this->sumOutstanding(clone $schedules)),
            'overdue_total' => Money::fromCents($this->sumOutstanding(clone $overdue)),
            'overdue_count' => (clone $overdue)->count(),
            'defaulted_count' => (clone $accounts)->where('loan_accounts.status', 'defaulted')->count(),
            'interest_collected' => Money::fromCents($this->sumAllocations($user, $filters, 'interest_amount')),
            'fees_collected' => Money::fromCents($this->sumAllocations($user, $filters, 'fees_amount') + $this->sumAllocations($user, $filters, 'penalty_amount')),
            'application_statuses' => (clone $apps)->select('status')->selectRaw('count(*) as total')->groupBy('status')->orderBy('status')->get(),
            'account_statuses' => (clone $accounts)->select('loan_accounts.status')->selectRaw('count(*) as total')->groupBy('loan_accounts.status')->orderBy('loan_accounts.status')->get(),
            'aging' => $this->aging(clone $overdue),
            'monthly_trends' => $this->monthlyTrends($user, $filters),
        ];
    }

    /** @return array<string, mixed> */
    public function options(User $user): array
    {
        $borrowers = Borrower::query()->whereHas('applications', function (Builder $query) use ($user): void {
            if ($user->role === 'loan_officer') {
                $query->where('assigned_user_id', $user->id);
            }
        })->orderBy('full_name')->get(['id', 'full_name']);

        return [
            'products' => LoanProduct::query()->orderBy('name')->get(['id', 'name']),
            'officers' => $user->role === 'loan_officer' ? collect([$user]) : User::query()->where('role', 'loan_officer')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'borrowers' => $borrowers,
        ];
    }

    /** @param array<string, mixed> $filters
     * @return Builder<LoanApplication>
     */
    public function applications(User $user, array $filters): Builder
    {
        $query = LoanApplication::query()->with(['borrower', 'product', 'assignedOfficer']);
        $this->filterApplications($query, $user, $filters);
        $this->dateRange($query, 'created_at', $filters);

        if (! empty($filters['application_status'])) {
            $query->where('status', $filters['application_status']);
        }
        if ($user->role === 'finance_officer') {
            $query->whereIn('status', ['approved', 'finance_processing', 'pending_disbursement', 'disbursed', 'active', 'fully_repaid']);
        }

        return $query;
    }

    /** @param array<string, mixed> $filters
     * @return Builder<LoanAccount>
     */
    public function accounts(User $user, array $filters): Builder
    {
        $query = LoanAccount::query()->with(['borrower', 'application.product', 'application.assignedOfficer', 'schedules', 'payments.allocations', 'disbursements.recorder', 'charges']);
        $this->filterAccounts($query, $user, $filters);
        $this->dateRange($query, 'loan_accounts.disbursed_at', $filters);
        if (! empty($filters['loan_status'])) {
            $query->where('loan_accounts.status', $filters['loan_status']);
        }

        return $query;
    }

    /** @param array<string, mixed> $filters
     * @return Builder<Payment>
     */
    public function payments(User $user, array $filters): Builder
    {
        $query = Payment::query()->with(['loanAccount.borrower', 'loanAccount.application.product', 'recorder', 'allocations']);
        $query->whereHas('loanAccount', fn (Builder $accounts) => $this->filterAccounts($accounts, $user, $filters));
        $this->dateRange($query, 'payments.paid_on', $filters);

        return $query;
    }

    /** @param array<string, mixed> $filters
     * @return Builder<Disbursement>
     */
    public function disbursements(User $user, array $filters): Builder
    {
        $query = Disbursement::query()->with(['loanAccount.borrower', 'loanAccount.application.product', 'recorder']);
        $query->whereHas('loanAccount', fn (Builder $accounts) => $this->filterAccounts($accounts, $user, $filters));
        $this->dateRange($query, 'disbursements.disbursed_on', $filters);

        return $query;
    }

    /** @param array<string, mixed> $filters
     * @return Builder<RepaymentSchedule>
     */
    public function schedules(User $user, array $filters): Builder
    {
        $query = RepaymentSchedule::query()->with(['loanAccount.borrower', 'loanAccount.application.product']);
        $query->whereHas('loanAccount', fn (Builder $accounts) => $this->filterAccounts($accounts, $user, $filters));
        $this->dateRange($query, 'repayment_schedules.due_date', $filters);

        return $query;
    }

    /** @param array<string, mixed> $filters
     * @return Builder<LoanApplication>|Builder<LoanAccount>|Builder<Payment>|Builder<Disbursement>|Builder<RepaymentSchedule>
     */
    public function query(string $type, User $user, array $filters): Builder
    {
        return match ($type) {
            'applications' => $this->applications($user, $filters),
            'disbursements' => $this->disbursements($user, $filters),
            'repayments' => $this->payments($user, $filters),
            'outstanding', 'borrower_statement' => $this->accounts($user, $filters),
            'overdue' => $this->schedules($user, $filters)->whereDate('due_date', '<', today())->whereIn('status', ['pending', 'partial', 'overdue']),
            default => throw new \InvalidArgumentException('Unsupported report type.'),
        };
    }

    /** @param array<string, mixed> $filters
     * @return array<int, array<int, int|float|string|null>>
     */
    public function displayRows(string $type, User $user, array $filters): array
    {
        if ($type === 'borrower_statement') {
            $rows = [];
            foreach ($this->statementRows($user, $filters) as $row) {
                $rows[] = $row;
                if (count($rows) === 100) {
                    break;
                }
            }

            return $rows;
        }

        $query = $this->query($type, $user, $filters)->orderByDesc('id')->limit(100);

        return $this->mapRows($type, $query->get());
    }

    /** @param array<string, mixed> $filters
     * @return \Generator<int, array<int, int|float|string|null>>
     */
    public function exportRows(string $type, User $user, array $filters): \Generator
    {
        if ($type === 'borrower_statement') {
            yield from $this->statementRows($user, $filters);

            return;
        }

        foreach ($this->query($type, $user, $filters)->reorder('id')->lazyById(200) as $record) {
            yield $this->mapRow($type, $record);
        }
    }

    /** @param iterable<object> $records
     * @return array<int, array<int, int|float|string|null>>
     */
    private function mapRows(string $type, iterable $records): array
    {
        $rows = [];
        foreach ($records as $record) {
            $rows[] = $this->mapRow($type, $record);
        }

        return $rows;
    }

    /** @return array<int, int|float|string|null> */
    private function mapRow(string $type, object $record): array
    {
        return match ($type) {
            'applications' => [$record->reference, $record->borrower->full_name, $record->product->name, (string) $record->requested_amount, $record->approved_amount === null ? '' : (string) $record->approved_amount, $record->status, $record->assignedOfficer?->name, $record->created_at->toDateTimeString()],
            'disbursements' => [$record->loanAccount->reference, $record->loanAccount->borrower->full_name, $record->loanAccount->application->product->name, (string) $record->gross_amount, (string) $record->amount, (string) $record->fees_withheld, $record->disbursed_on->toDateString(), $record->recorder->name, $record->provider_reference],
            'repayments' => [$record->id, $record->loanAccount->reference, $record->loanAccount->borrower->full_name, (string) $record->amount, $this->allocationSum($record->allocations, 'principal_amount'), $this->allocationSum($record->allocations, 'interest_amount'), $this->allocationSum($record->allocations, 'fees_amount'), $this->allocationSum($record->allocations, 'penalty_amount'), $record->paid_on->toDateString(), $record->method, $record->external_reference, $record->status, $record->recorder->name],
            'outstanding' => $this->accountRow($record),
            'borrower_statement' => throw new \LogicException('Borrower statement rows use the transaction ledger formatter.'),
            'overdue' => $this->scheduleRow($record),
            default => throw new \InvalidArgumentException('Unsupported report type.'),
        };
    }

    /** @return array<int, int|float|string|null> */
    private function accountRow(LoanAccount $account): array
    {
        $parts = $this->outstandingParts($account);

        return [$account->reference, $account->borrower->full_name, $account->application->product->name, $parts['principal'], $parts['interest'], $parts['fees'], $parts['penalty'], $parts['total'], $account->status, $account->maturity_date?->toDateString()];
    }

    /** @return array<int, int|float|string|null> */
    private function scheduleRow(RepaymentSchedule $schedule): array
    {
        $parts = $this->outstandingPartsForSchedule($schedule);

        return [$schedule->loanAccount->reference, $schedule->loanAccount->borrower->full_name, $schedule->due_date->toDateString(), $schedule->installment_number, (int) $schedule->due_date->diffInDays(today()), $parts['principal'], $parts['interest'], $parts['fees'], $parts['penalty'], $parts['total'], $schedule->loanAccount->status];
    }

    /** @return array{principal: string, interest: string, fees: string, penalty: string, total: string} */
    private function outstandingParts(LoanAccount $account): array
    {
        $parts = ['principal' => 0, 'interest' => 0, 'fees' => 0, 'penalty' => 0];
        foreach ($account->schedules as $schedule) {
            foreach ($this->openParts($schedule) as $key => $amount) {
                $parts[$key] += $amount;
            }
        }

        return $this->moneyParts($parts);
    }

    /** @return array{principal: string, interest: string, fees: string, penalty: string, total: string} */
    private function outstandingPartsForSchedule(RepaymentSchedule $schedule): array
    {
        return $this->moneyParts($this->openParts($schedule));
    }

    /** @param array{principal: int, interest: int, fees: int, penalty: int} $parts
     * @return array{principal: string, interest: string, fees: string, penalty: string, total: string}
     */
    private function moneyParts(array $parts): array
    {
        return [
            'principal' => Money::fromCents($parts['principal']),
            'interest' => Money::fromCents($parts['interest']),
            'fees' => Money::fromCents($parts['fees']),
            'penalty' => Money::fromCents($parts['penalty']),
            'total' => Money::fromCents(array_sum($parts)),
        ];
    }

    /** @return array{principal: int, interest: int, fees: int, penalty: int} */
    private function openParts(RepaymentSchedule $schedule): array
    {
        return [
            'principal' => max(0, Money::toCents((string) $schedule->principal_due) - Money::toCents((string) $schedule->principal_paid)),
            'interest' => max(0, Money::toCents((string) $schedule->interest_due) - Money::toCents((string) $schedule->interest_paid)),
            'fees' => max(0, Money::toCents((string) $schedule->fees_due) - Money::toCents((string) $schedule->fees_paid)),
            'penalty' => max(0, Money::toCents((string) $schedule->penalties_due) - Money::toCents((string) $schedule->penalties_paid)),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return \Generator<int, array<int, int|float|string|null>>
     */
    private function statementRows(User $user, array $filters): \Generator
    {
        foreach ($this->accounts($user, $filters)->reorder('id')->lazyById(100) as $account) {
            $balance = $account->outstandingBalance();
            foreach ($account->disbursements->sortBy('disbursed_on') as $disbursement) {
                yield [$disbursement->disbursed_on->toDateString(), $account->reference, 'Disbursement confirmed', (string) $disbursement->gross_amount, '', (string) $disbursement->fees_withheld, '', (string) $disbursement->amount, $balance, trim(($disbursement->method ?? '').' '.$disbursement->provider_reference), 'confirmed'];
            }
            foreach ($account->schedules->sortBy('installment_number') as $schedule) {
                $scheduledCents = Money::toCents((string) $schedule->principal_due)
                    + Money::toCents((string) $schedule->interest_due)
                    + Money::toCents((string) $schedule->fees_due);
                yield [$schedule->due_date->toDateString(), $account->reference, 'Installment '.$schedule->installment_number.' due', (string) $schedule->principal_due, (string) $schedule->interest_due, (string) $schedule->fees_due, '', Money::fromCents($scheduledCents), $balance, 'Scheduled repayment', $schedule->status];
            }
            foreach ($account->charges->where('charge_type', 'late_penalty')->sortBy('charged_on') as $charge) {
                yield [$charge->charged_on->toDateString(), $account->reference, 'Late penalty accrued', '', '', '', (string) $charge->amount, (string) $charge->amount, $balance, $charge->notes, $charge->status];
            }
            foreach ($account->payments->sortBy('paid_on') as $payment) {
                yield [$payment->paid_on->toDateString(), $account->reference, $payment->status === 'reversed' ? 'Repayment reversed' : 'Repayment received', $this->allocationSum($payment->allocations, 'principal_amount'), $this->allocationSum($payment->allocations, 'interest_amount'), $this->allocationSum($payment->allocations, 'fees_amount'), $this->allocationSum($payment->allocations, 'penalty_amount'), (string) $payment->amount, $balance, trim(($payment->method ?? '').' '.$payment->external_reference.' '.$payment->reversal_reason), $payment->status];
            }
            foreach ($account->charges->where('status', 'withheld')->sortBy('charged_on') as $charge) {
                yield [$charge->charged_on->toDateString(), $account->reference, 'Fee withheld', '', '', (string) $charge->amount, '', (string) $charge->amount, $balance, $charge->notes, $charge->status];
            }
        }
    }

    /** @param iterable<object> $allocations */
    private function allocationSum(iterable $allocations, string $column): string
    {
        $total = 0;
        foreach ($allocations as $allocation) {
            $total += Money::toCents((string) $allocation->{$column});
        }

        return Money::fromCents($total);
    }

    private function sumMoney(Builder $query, string $column): int
    {
        $total = 0;
        foreach ($query->with([])->select(['id', $column])->lazyById(500) as $row) {
            $total += Money::toCents((string) $row->{$column});
        }

        return $total;
    }

    private function sumOutstanding(Builder $query): int
    {
        $total = 0;
        foreach ($query->with([])->select(['id', 'principal_due', 'principal_paid', 'interest_due', 'interest_paid', 'fees_due', 'fees_paid', 'penalties_due', 'penalties_paid'])->lazyById(500) as $row) {
            $total += array_sum($this->openParts($row));
        }

        return $total;
    }

    private function scheduledDueCents(RepaymentSchedule $schedule): int
    {
        return Money::toCents((string) $schedule->principal_due)
            + Money::toCents((string) $schedule->interest_due)
            + Money::toCents((string) $schedule->fees_due);
    }

    /** @param array<string, mixed> $filters */
    private function sumAllocations(User $user, array $filters, string $column): int
    {
        $paymentIds = $this->payments($user, $filters)->where('payments.status', 'posted')->select('payments.id');
        $total = 0;
        foreach (PaymentAllocation::query()->whereIn('payment_id', $paymentIds)->select(['id', $column])->lazyById(500) as $row) {
            $total += Money::toCents((string) $row->{$column});
        }

        return $total;
    }

    /** @return array<string, array{count: int, amount: string}> */
    private function aging(Builder $query): array
    {
        $buckets = ['1-30 days' => ['count' => 0, 'amount' => 0], '31-60 days' => ['count' => 0, 'amount' => 0], '61-90 days' => ['count' => 0, 'amount' => 0], 'Over 90 days' => ['count' => 0, 'amount' => 0]];
        foreach ($query->with([])->select(['id', 'due_date', 'principal_due', 'principal_paid', 'interest_due', 'interest_paid', 'fees_due', 'fees_paid', 'penalties_due', 'penalties_paid'])->lazyById(500) as $row) {
            $days = (int) $row->due_date->diffInDays(today());
            $key = match (true) {
                $days <= 30 => '1-30 days',
                $days <= 60 => '31-60 days',
                $days <= 90 => '61-90 days',
                default => 'Over 90 days',
            };
            $buckets[$key]['count']++;
            $buckets[$key]['amount'] += array_sum($this->openParts($row));
        }
        foreach ($buckets as &$bucket) {
            $bucket['amount'] = Money::fromCents($bucket['amount']);
        }

        return $buckets;
    }

    /** @param array<string, mixed> $filters
     * @return array<int, array{month: string, disbursed: string, collected: string, expected: string}>
     */
    private function monthlyTrends(User $user, array $filters): array
    {
        $firstMonth = CarbonImmutable::today()->startOfMonth()->subMonths(5);
        $lastMonth = CarbonImmutable::today()->endOfMonth();
        $months = [];
        for ($month = $firstMonth; $month <= $lastMonth; $month = $month->addMonth()) {
            $months[$month->format('Y-m')] = ['month' => $month->format('M Y'), 'disbursed' => 0, 'collected' => 0, 'expected' => 0];
        }

        $disbursements = $this->disbursements($user, $filters)->with([])->whereBetween('disbursed_on', [$firstMonth->toDateString(), $lastMonth->toDateString()])->select(['id', 'disbursed_on', 'amount']);
        foreach ($disbursements->lazyById(500) as $row) {
            $months[$row->disbursed_on->format('Y-m')]['disbursed'] += Money::toCents((string) $row->amount);
        }
        $payments = $this->payments($user, $filters)->with([])->where('payments.status', 'posted')->whereBetween('payments.paid_on', [$firstMonth->toDateString(), $lastMonth->toDateString()])->select(['payments.id', 'payments.paid_on', 'payments.amount']);
        foreach ($payments->lazyById(500, 'payments.id') as $row) {
            $months[$row->paid_on->format('Y-m')]['collected'] += Money::toCents((string) $row->amount);
        }
        $schedules = $this->schedules($user, $filters)->with([])->whereBetween('due_date', [$firstMonth->toDateString(), $lastMonth->toDateString()])->select(['repayment_schedules.id', 'due_date', 'principal_due', 'principal_paid', 'interest_due', 'interest_paid', 'fees_due', 'fees_paid', 'penalties_due', 'penalties_paid']);
        foreach ($schedules->lazyById(500, 'repayment_schedules.id') as $row) {
            $months[$row->due_date->format('Y-m')]['expected'] += $this->scheduledDueCents($row);
        }
        foreach ($months as &$values) {
            $values['disbursed'] = Money::fromCents($values['disbursed']);
            $values['collected'] = Money::fromCents($values['collected']);
            $values['expected'] = Money::fromCents($values['expected']);
        }

        return array_values($months);
    }

    /** @param array<string, mixed> $filters */
    private function filterApplications(Builder $query, User $user, array $filters): void
    {
        if ($user->role === 'loan_officer') {
            $query->where('assigned_user_id', $user->id);
        } elseif (! empty($filters['officer_id'])) {
            $query->where('assigned_user_id', $filters['officer_id']);
        }
        if (! empty($filters['product_id'])) {
            $query->where('loan_product_id', $filters['product_id']);
        }
        if (! empty($filters['borrower_id'])) {
            $query->where('borrower_id', $filters['borrower_id']);
        }
    }

    /** @param array<string, mixed> $filters */
    private function filterAccounts(Builder $query, User $user, array $filters): void
    {
        if ($user->role === 'loan_officer') {
            $query->whereHas('application', fn (Builder $applications) => $applications->where('assigned_user_id', $user->id));
        }
        if (! empty($filters['product_id'])) {
            $query->whereHas('application', fn (Builder $applications) => $applications->where('loan_product_id', $filters['product_id']));
        }
        if (! empty($filters['officer_id']) && $user->role !== 'loan_officer') {
            $query->whereHas('application', fn (Builder $applications) => $applications->where('assigned_user_id', $filters['officer_id']));
        }
        if (! empty($filters['borrower_id'])) {
            $query->where('loan_accounts.borrower_id', $filters['borrower_id']);
        }
        if (! empty($filters['loan_status'])) {
            $query->where('loan_accounts.status', $filters['loan_status']);
        }
    }

    /** @param array<string, mixed> $filters */
    private function dateRange(Builder $query, string $column, array $filters): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }
    }
}
