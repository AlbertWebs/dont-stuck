<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['reference', 'loan_application_id', 'borrower_id', 'principal_amount', 'disbursed_amount', 'interest_rate', 'interest_basis', 'processing_fee', 'commissioning_fee', 'daily_late_fee', 'late_penalty_start', 'allocation_order', 'fee_collection_method', 'default_threshold_days', 'term_months', 'disbursed_at', 'maturity_date', 'status'])]
class LoanAccount extends Model
{
    public const OPEN_STATUSES = ['active', 'partially_repaid', 'overdue', 'defaulted'];

    protected function casts(): array
    {
        return ['disbursed_at' => 'date', 'maturity_date' => 'date'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(RepaymentSchedule::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(Disbursement::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(LoanCharge::class);
    }

    public function outstandingBalance(): string
    {
        $cents = $this->schedules->sum(fn (RepaymentSchedule $schedule): int => Money::toCents($schedule->outstandingAmount()));

        return Money::fromCents($cents);
    }
}
