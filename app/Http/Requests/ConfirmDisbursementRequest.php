<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConfirmDisbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['administrator', 'finance_officer']) ?? false;
    }

    public function rules(): array
    {
        return [
            'disbursed_on' => ['required', 'date', 'before_or_equal:today'],
            'first_repayment_due_on' => ['required', 'date', 'after:disbursed_on'],
            'method' => ['required', 'in:bank_transfer,cash,manual_mpesa,other'],
            'provider_reference' => ['nullable', 'string', 'max:160', 'unique:disbursements,provider_reference'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['disbursed_on', 'first_repayment_due_on'])) {
                return;
            }

            if ($this->date('first_repayment_due_on')->lessThanOrEqualTo($this->date('disbursed_on'))) {
                $validator->errors()->add('first_repayment_due_on', 'The first installment must be due after disbursement.');
            }
        }];
    }
}
