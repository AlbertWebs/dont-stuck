<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'minimum_amount', 'maximum_amount', 'repayment_months', 'interest_rate', 'interest_basis', 'processing_fee_rate', 'commissioning_fee', 'daily_late_fee', 'late_penalty_start', 'allocation_order', 'fee_collection_method', 'default_threshold_days', 'is_active'])]
class LoanProduct extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }
}
