<?php

namespace App\Services;

use App\Models\LoanAccount;
use App\Models\RepaymentSchedule;
use App\Support\Money;
use Carbon\CarbonImmutable;

class RepaymentPerformanceService
{
    /**
     * @return array{
     *     total_installments: int,
     *     paid_installments: int,
     *     remaining_installments: int,
     *     overdue_installments: int,
     *     on_time_installments: int,
     *     late_or_unpaid_installments: int,
     *     evaluated_installments: int,
     *     on_time_percentage: ?float,
     *     rating: string,
     *     rating_tone: string,
     *     total_repaid: string,
     *     paid_on_by_schedule: array<int, string>
     * }
     */
    public function summarize(LoanAccount $loanAccount): array
    {
        $schedules = $loanAccount->schedules;
        $paymentDatesBySchedule = [];
        $totalRepaidCents = 0;

        foreach ($loanAccount->payments as $payment) {
            if ($payment->status !== 'posted') {
                continue;
            }

            $totalRepaidCents += Money::toCents((string) $payment->amount);
            $paidOn = $payment->paid_on?->toDateString();

            if ($paidOn === null) {
                continue;
            }

            foreach ($payment->allocations as $allocation) {
                $scheduleId = (int) $allocation->repayment_schedule_id;

                if (! isset($paymentDatesBySchedule[$scheduleId]) || $paidOn > $paymentDatesBySchedule[$scheduleId]) {
                    $paymentDatesBySchedule[$scheduleId] = $paidOn;
                }
            }
        }

        $today = today();
        $paidInstallments = $schedules->filter(fn (RepaymentSchedule $schedule): bool => Money::toCents($schedule->outstandingAmount()) === 0)->count();
        $evaluatedSchedules = $schedules->filter(fn (RepaymentSchedule $schedule): bool => $schedule->due_date->lte($today)
            || Money::toCents($schedule->outstandingAmount()) === 0);
        $overdueInstallments = $schedules->filter(fn (RepaymentSchedule $schedule): bool => $schedule->due_date->lt($today)
            && Money::toCents($schedule->outstandingAmount()) > 0)->count();
        $onTimeInstallments = $evaluatedSchedules->filter(function (RepaymentSchedule $schedule) use ($paymentDatesBySchedule): bool {
            $paidOn = $paymentDatesBySchedule[$schedule->id] ?? null;

            return Money::toCents($schedule->outstandingAmount()) === 0
                && $paidOn !== null
                && $paidOn <= $schedule->due_date->toDateString();
        })->count();
        $evaluatedInstallments = $evaluatedSchedules->count();
        $onTimePercentage = $evaluatedInstallments > 0
            ? round(($onTimeInstallments / $evaluatedInstallments) * 100, 1)
            : null;

        [$rating, $ratingTone] = match (true) {
            $onTimePercentage === null => ['No history yet', 'neutral'],
            $onTimePercentage >= 90 => ['On track', 'positive'],
            $onTimePercentage >= 75 => ['Mostly on time', 'positive'],
            $onTimePercentage >= 50 => ['Mixed record', 'caution'],
            default => ['Needs attention', 'concern'],
        };

        return [
            'total_installments' => $schedules->count(),
            'paid_installments' => $paidInstallments,
            'remaining_installments' => max(0, $schedules->count() - $paidInstallments),
            'overdue_installments' => $overdueInstallments,
            'on_time_installments' => $onTimeInstallments,
            'late_or_unpaid_installments' => max(0, $evaluatedInstallments - $onTimeInstallments),
            'evaluated_installments' => $evaluatedInstallments,
            'on_time_percentage' => $onTimePercentage,
            'rating' => $rating,
            'rating_tone' => $ratingTone,
            'total_repaid' => Money::fromCents($totalRepaidCents),
            'paid_on_by_schedule' => array_map(
                fn (string $paidOn): string => CarbonImmutable::parse($paidOn)->format('d M Y'),
                $paymentDatesBySchedule,
            ),
        ];
    }
}
