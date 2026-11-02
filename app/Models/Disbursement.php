<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['loan_account_id', 'amount', 'gross_amount', 'fees_withheld', 'disbursed_on', 'method', 'provider_reference', 'recorded_by', 'notes'])]
class Disbursement extends Model
{
    protected function casts(): array
    {
        return ['disbursed_on' => 'date'];
    }

    public function loanAccount(): BelongsTo
    {
        return $this->belongsTo(LoanAccount::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
