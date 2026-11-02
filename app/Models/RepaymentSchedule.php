<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['loan_account_id', 'installment_number', 'due_date', 'principal_due', 'interest_due', 'fees_due', 'principal_paid', 'interest_paid', 'fees_paid', 'penalties_due', 'penalties_paid', 'penalty_days_accrued', 'status'])]
class RepaymentSchedule extends Model
{
    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function loanAccount(): BelongsTo
    {
        return $this->belongsTo(LoanAccount::class);
    }

    public function outstandingAmount(): string
    {
        $due = Money::toCents((string) $this->principal_due)
            + Money::toCents((string) $this->interest_due)
            + Money::toCents((string) $this->fees_due)
            + Money::toCents((string) $this->penalties_due);
        $paid = Money::toCents((string) $this->principal_paid)
            + Money::toCents((string) $this->interest_paid)
            + Money::toCents((string) $this->fees_paid)
            + Money::toCents((string) $this->penalties_paid);

        return Money::fromCents(max(0, $due - $paid));
    }
}
