<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['full_name', 'national_id', 'phone', 'email', 'residence', 'employer', 'employer_location', 'job_title', 'net_salary', 'next_of_kin_name', 'next_of_kin_id', 'next_of_kin_relation', 'next_of_kin_phone'])]
class Borrower extends Model
{
    use HasFactory;

    public function applications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function loanAccounts(): HasMany
    {
        return $this->hasMany(LoanAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
