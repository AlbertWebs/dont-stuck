<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'repayment_schedule_id', 'principal_amount', 'interest_amount', 'fees_amount', 'penalty_amount'])]
class PaymentAllocation extends Model
{
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RepaymentSchedule::class, 'repayment_schedule_id');
    }
}
