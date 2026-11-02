<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'national_id' => ['required', 'string', 'max:40'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
