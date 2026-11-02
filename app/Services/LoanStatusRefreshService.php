<?php

namespace App\Services;

use App\Models\LoanAccount;
use App\Models\RepaymentSchedule;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class LoanStatusRefreshService
{
    public function __construct(private readonly PenaltyAccrualService $penalties) {}

    public function refresh(): int
    {
        $updated = 0;

        LoanAccount::query()
            ->whereIn('status', ['active', 'partially_repaid', 'overdue', 'defaulted'])
            ->with('schedules')
            ->chunkById(100, function ($accounts) use (&$updated): void {
                foreach ($accounts as $account) {
                    DB::transaction(function () use ($account, &$updated): void {
                        $locked = LoanAccount::query()->lockForUpdate()->find($account->id);

                        if (! $locked) {
                            return;
                        }

                        $schedules = RepaymentSchedule::query()
                            ->where('loan_account_id', $locked->id)
                            ->orderBy('due_date')
                            ->lockForUpdate()
                            ->get();
                        $today = today();
                        $overdueSchedules = collect();

                        foreach ($schedules as $schedule) {
                            $this->penalties->accrueThrough($locked, $schedule, CarbonImmutable::parse($today));
                            $schedule->refresh();
                            $outstanding = $this->outstandingCents($schedule);
                            $isOverdue = $schedule->due_date->lt($today) && $outstanding > 0;
                            $status = $outstanding === 0 ? 'paid' : ($isOverdue ? 'overdue' : ($this->paidCents($schedule) > 0 ? 'partial' : 'pending'));

                            if ($schedule->status !== $status) {
                                $schedule->update(['status' => $status]);
                                $updated++;
                            }

                            if ($isOverdue) {
                                $overdueSchedules->push($schedule);
                            }
                        }

                        $hasPostedPayments = $locked->payments()->where('status', 'posted')->exists();

                        if ($overdueSchedules->isNotEmpty()) {
                            $daysPastDue = (int) $overdueSchedules->min('due_date')->diffInDays($today);
                            $threshold = $locked->default_threshold_days;
                            $status = $threshold !== null && $daysPastDue >= $threshold ? 'defaulted' : 'overdue';
                        } else {
                            $status = $hasPostedPayments ? 'partially_repaid' : 'active';
                        }

                        if ($locked->status !== $status) {
                            $locked->update(['status' => $status]);
                            $updated++;
                        }
                    });
                }
            });

        return $updated;
    }

    private function outstandingCents(RepaymentSchedule $schedule): int
    {
        return max(0, Money::toCents((string) $schedule->principal_due) - Money::toCents((string) $schedule->principal_paid))
            + max(0, Money::toCents((string) $schedule->interest_due) - Money::toCents((string) $schedule->interest_paid))
            + max(0, Money::toCents((string) $schedule->fees_due) - Money::toCents((string) $schedule->fees_paid))
            + max(0, Money::toCents((string) $schedule->penalties_due) - Money::toCents((string) $schedule->penalties_paid));
    }

    private function paidCents(RepaymentSchedule $schedule): int
    {
        return Money::toCents((string) $schedule->principal_paid)
            + Money::toCents((string) $schedule->interest_paid)
            + Money::toCents((string) $schedule->fees_paid)
            + Money::toCents((string) $schedule->penalties_paid);
    }
}
