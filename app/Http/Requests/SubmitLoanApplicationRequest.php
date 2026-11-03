<?php

namespace App\Http\Requests;

use App\Models\LoanProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubmitLoanApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loan_product_id' => ['required', 'integer', Rule::exists('loan_products', 'id')->where('is_active', true)],
            'full_name' => ['required', 'string', 'max:160'],
            'national_id' => ['required', 'string', 'max:40'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'residence' => ['required', 'string', 'max:255'],
            'employer' => ['nullable', 'string', 'max:255'],
            'employer_location' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:160'],
            'net_salary' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'next_of_kin_name' => ['required', 'string', 'max:160'],
            'next_of_kin_id' => ['required', 'string', 'max:40'],
            'next_of_kin_relation' => ['required', 'string', 'max:80'],
            'next_of_kin_phone' => ['required', 'string', 'max:32'],
            'requested_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'requested_months' => ['required', 'integer', 'between:1,3'],
            'purpose' => ['required', 'string', 'min:10', 'max:2000'],
            'applicant_notes' => ['nullable', 'string', 'max:2000'],
            'guarantors' => ['required', 'array', 'size:2'],
            'guarantors.*.full_name' => ['required', 'string', 'max:160'],
            'guarantors.*.national_id' => ['required', 'string', 'max:40'],
            'guarantors.*.phone' => ['required', 'string', 'max:32'],
            'guarantors.*.relationship' => ['nullable', 'string', 'max:80'],
            'guarantors.*.is_colleague' => ['nullable', 'boolean'],
            'collateral_items' => ['nullable', 'array', 'max:10'],
            'collateral_items.*.description' => ['required', 'string', 'max:255'],
            'collateral_items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'collateral_items.*.make_model' => ['nullable', 'string', 'max:160'],
            'collateral_items.*.serial_number' => ['nullable', 'string', 'max:160'],
            'collateral_items.*.estimated_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'documents' => ['nullable', 'array'],
            'documents.national_id' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:25600'],
            'documents.appointment_letter' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:25600'],
            'documents.payslips' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:25600'],
            'documents.bank_statement' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:25600'],
            'documents.mpesa_statement' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:25600'],
            'documents.guarantor_ids' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:25600'],
            'privacy_consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documents.*.max' => 'Each document must be no larger than 25 MB.',
            'documents.*.mimes' => 'Each document must be a PDF, JPG or PNG file.',
            'documents.*.extensions' => 'Each document must use a PDF, JPG or PNG filename extension.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('collateral_items', []))
            ->filter(fn (array $item): bool => filled($item['description'] ?? null))
            ->values()
            ->all();

        $this->merge(['collateral_items' => $items]);
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['loan_product_id', 'requested_amount'])) {
                return;
            }

            $product = LoanProduct::query()->find($this->integer('loan_product_id'));

            if (! $product) {
                return;
            }

            if ($product->repayment_months !== null && (int) $this->input('requested_months') !== $product->repayment_months) {
                $validator->errors()->add('requested_months', 'Choose the repayment period configured for this loan option.');
            }

            if ($product->repayment_months !== null && ! in_array((int) $product->repayment_months, [1, 2, 3], true)) {
                $validator->errors()->add('loan_product_id', 'This loan option is unavailable for new applications until its repayment period is set to 1–3 months.');
            }

            $amount = (string) $this->input('requested_amount');

            if ($product->minimum_amount !== null && $this->toCents($amount) < $this->toCents((string) $product->minimum_amount)) {
                $validator->errors()->add('requested_amount', 'The requested amount is below the minimum for this loan option.');
            }

            if ($product->maximum_amount !== null && $this->toCents($amount) > $this->toCents((string) $product->maximum_amount)) {
                $validator->errors()->add('requested_amount', 'The requested amount is above the maximum for this loan option.');
            }
        }];
    }

    private function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
