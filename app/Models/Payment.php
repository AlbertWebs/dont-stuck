<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['loan_account_id', 'amount', 'paid_on', 'method', 'external_reference', 'recorded_by', 'status', 'notes', 'reversed_by', 'reversed_at', 'reversal_reason'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return ['paid_on' => 'date', 'reversed_at' => 'datetime'];
    }

    public function loanAccount(): BelongsTo
    {
        return $this->belongsTo(LoanAccount::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
