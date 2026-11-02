<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['administrator', 'finance_officer']) ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', 'in:cash,bank_transfer,manual_mpesa,other'],
            'external_reference' => ['nullable', 'string', 'max:160', 'unique:payments,external_reference'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
