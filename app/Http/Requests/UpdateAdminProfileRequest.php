<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Rules\Unique;

class UpdateAdminProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['administrator', 'loan_officer', 'finance_officer', 'management']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string|Unique|RequiredIf>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user())],
            'current_password' => [Rule::requiredIf($this->filled('password')), 'current_password'],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ];
    }
}
