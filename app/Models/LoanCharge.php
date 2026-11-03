<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['loan_account_id', 'repayment_schedule_id', 'charge_type', 'amount', 'amount_paid', 'charged_on', 'status', 'paid_at', 'recorded_by', 'notes'])]
class LoanCharge extends Model
{
    protected function casts(): array
    {
        return ['charged_on' => 'date', 'paid_at' => 'datetime'];
    }

    public function loanAccount(): BelongsTo
    {
        return $this->belongsTo(LoanAccount::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RepaymentSchedule::class, 'repayment_schedule_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
