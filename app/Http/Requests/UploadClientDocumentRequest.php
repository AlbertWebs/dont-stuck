<?php

namespace App\Http\Requests;

use App\Models\LoanApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadClientDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application instanceof LoanApplication
            && $this->user()?->role === 'client'
            && $this->user()?->borrower?->is($application->borrower);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'in:identity,income,residence,bank_statement,other'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'extensions:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
