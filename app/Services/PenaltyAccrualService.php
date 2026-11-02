<?php

namespace App\Services;

use App\Models\LoanAccount;
use App\Models\LoanCharge;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PenaltyAccrualService
{
    public function accrueThrough(LoanAccount $account, RepaymentSchedule $schedule, CarbonImmutable $throughDate, ?User $actor = null): int
    {
        $dueDate = CarbonImmutable::parse($schedule->due_date)->startOfDay();
        if ($throughDate->lt($dueDate)) {
            return 0;
        }

        $targetDays = (int) $dueDate->diffInDays($throughDate);
        if ($account->late_penalty_start === 'on_due_date') {
            $targetDays++;
        }

        if ($targetDays < $schedule->penalty_days_accrued) {
            throw ValidationException::withMessages(['paid_on' => 'This payment date is before late fees already accrued to the account.']);
        }

        $baseCents = max(0, Money::toCents((string) $schedule->principal_due) - Money::toCents((string) $schedule->principal_paid))
            + max(0, Money::toCents((string) $schedule->interest_due) - Money::toCents((string) $schedule->interest_paid))
            + max(0, Money::toCents((string) $schedule->fees_due) - Money::toCents((string) $schedule->fees_paid));
        $additionalDays = max(0, $targetDays - $schedule->penalty_days_accrued);

        if ($additionalDays === 0 || $baseCents === 0) {
            return 0;
        }

        $penaltyCents = Money::toCents((string) $account->daily_late_fee) * $additionalDays;
        $schedule->update([
            'penalties_due' => Money::fromCents(Money::toCents((string) $schedule->penalties_due) + $penaltyCents),
            'penalty_days_accrued' => $targetDays,
        ]);

        if ($penaltyCents > 0) {
            LoanCharge::create([
                'loan_account_id' => $account->id,
                'repayment_schedule_id' => $schedule->id,
                'charge_type' => 'late_penalty',
                'amount' => Money::fromCents($penaltyCents),
                'amount_paid' => '0.00',
                'charged_on' => $throughDate->toDateString(),
                'status' => 'due',
                'recorded_by' => $actor?->id,
                'notes' => 'Daily late fee accrued for '.$additionalDays.' calendar day(s).',
            ]);
        }

        return $penaltyCents;
    }
}
