<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['loan_application_id', 'full_name', 'national_id', 'phone', 'relationship', 'is_colleague', 'email', 'address', 'work_phone', 'work_station', 'residence', 'building_name', 'house_number', 'guarantee_amount', 'guarantee_amount_words', 'signature_name', 'signed_on', 'joint_and_several_accepted'])]
class Guarantor extends Model
{
    protected function casts(): array
    {
        return ['is_colleague' => 'boolean', 'joint_and_several_accepted' => 'boolean', 'signed_on' => 'date'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }
}
