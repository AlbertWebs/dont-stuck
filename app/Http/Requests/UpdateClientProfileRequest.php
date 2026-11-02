<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->role === 'client' && $user->borrower()->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:32'],
            'residence' => ['nullable', 'string', 'max:255'],
            'employer' => ['nullable', 'string', 'max:255'],
            'employer_location' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:160'],
            'net_salary' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'next_of_kin_name' => ['nullable', 'string', 'max:160'],
            'next_of_kin_id' => ['nullable', 'string', 'max:40'],
            'next_of_kin_relation' => ['nullable', 'string', 'max:80'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}
