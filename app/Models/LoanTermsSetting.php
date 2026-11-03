<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['interest_rate', 'interest_basis', 'processing_fee_rate', 'legal_fee_amount', 'updated_by'])]
class LoanTermsSetting extends Model
{
    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:4',
            'processing_fee_rate' => 'decimal:4',
            'legal_fee_amount' => 'decimal:2',
        ];
    }
}
