<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['loan_application_id', 'full_name', 'national_id', 'phone', 'relationship', 'is_colleague'])]
class Guarantor extends Model
{
    protected function casts(): array
    {
        return ['is_colleague' => 'boolean'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }
}
