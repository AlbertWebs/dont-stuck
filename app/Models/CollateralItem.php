<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['loan_application_id', 'description', 'quantity', 'make_model', 'serial_number', 'estimated_value', 'color', 'ownership_confirmed'])]
class CollateralItem extends Model
{
    protected function casts(): array
    {
        return ['ownership_confirmed' => 'boolean'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }
}
