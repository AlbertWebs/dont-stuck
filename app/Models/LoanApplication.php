<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['reference', 'borrower_id', 'loan_product_id', 'assigned_user_id', 'requested_amount', 'requested_months', 'approved_amount', 'approved_months', 'purpose', 'status', 'consented_at', 'applicant_notes', 'decision_notes', 'application_data'])]
class LoanApplication extends Model
{
    use HasFactory;

    public const CLIENT_DOCUMENT_UPLOAD_STATUSES = [
        'submitted',
        'under_review',
        'verification_assessment',
        'additional_information_required',
    ];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'application_data' => 'array'];
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ApplicationReview::class);
    }

    public function guarantors(): HasMany
    {
        return $this->hasMany(Guarantor::class);
    }

    public function collateralItems(): HasMany
    {
        return $this->hasMany(CollateralItem::class);
    }

    public function loanAccount(): HasOne
    {
        return $this->hasOne(LoanAccount::class);
    }

    public function allowsClientDocumentUpload(): bool
    {
        return in_array($this->status, self::CLIENT_DOCUMENT_UPLOAD_STATUSES, true);
    }

    public function clientStatusLabel(): string
    {
        return match ($this->status) {
            'submitted' => 'Submitted',
            'under_review', 'verification_assessment', 'pending_approval' => 'Under review',
            'additional_information_required' => 'Documents required',
            'approved', 'finance_processing', 'pending_disbursement' => 'Approved',
            'rejected' => 'Declined',
            'on_hold' => 'On hold',
            'disbursed', 'active' => 'Disbursed',
            default => ucwords(str_replace('_', ' ', $this->status)),
        };
    }
}
