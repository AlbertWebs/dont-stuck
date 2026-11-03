<?php

namespace App\Http\Requests;

use App\Models\LoanProduct;
use App\Support\KenyanMoneyWords;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
        $requiredDocument = ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:25600'];

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
            'residence_details.estate' => ['required', 'string', 'max:160'],
            'residence_details.building_name' => ['required', 'string', 'max:160'],
            'residence_details.house_number' => ['required', 'string', 'max:80'],
            'residence_details.city_town' => ['required', 'string', 'max:160'],
            'residence_details.nearest_landmark' => ['required', 'string', 'max:255'],
            'employment_details.employer_name' => ['required', 'string', 'max:255'],
            'employment_details.location_branch' => ['required', 'string', 'max:255'],
            'employment_details.road_street' => ['required', 'string', 'max:160'],
            'employment_details.building' => ['required', 'string', 'max:160'],
            'employment_details.floor_office' => ['required', 'string', 'max:100'],
            'employment_details.telephone' => ['required', 'string', 'max:32'],
            'employment_details.specific_title' => ['required', 'string', 'max:160'],
            'employment_details.department' => ['required', 'string', 'max:160'],
            'employment_details.net_salary' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'requested_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'requested_amount_words' => ['required', 'string', 'max:255'],
            'requested_months' => ['required', 'integer', 'between:1,3'],
            'purpose' => ['required', 'string', 'min:10', 'max:2000'],
            'applicant_notes' => ['nullable', 'string', 'max:2000'],
            'guarantors' => ['required', 'array', 'size:2'],
            'guarantors.*.full_name' => ['required', 'string', 'max:160'],
            'guarantors.*.national_id' => ['required', 'string', 'max:40'],
            'guarantors.*.phone' => ['required', 'string', 'max:32'],
            'guarantors.*.email' => ['nullable', 'email', 'max:255'],
            'guarantors.*.relationship' => ['required', 'string', 'max:80'],
            'guarantors.*.is_colleague' => ['nullable', 'boolean'],
            'guarantors.*.address' => ['required', 'string', 'max:255'],
            'guarantors.*.work_phone' => ['required', 'string', 'max:32'],
            'guarantors.*.work_station' => ['required', 'string', 'max:160'],
            'guarantors.*.residence' => ['required', 'string', 'max:160'],
            'guarantors.*.building_name' => ['required', 'string', 'max:160'],
            'guarantors.*.house_number' => ['required', 'string', 'max:80'],
            'guarantors.*.signature_name' => ['required', 'string', 'max:160'],
            'guarantors.*.signed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'guarantors.*.joint_and_several_accepted' => ['accepted'],
            'collateral_items' => ['nullable', 'array', 'max:10'],
            'collateral_items.*.description' => ['required', 'string', 'max:255'],
            'collateral_items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'collateral_items.*.make_model' => ['required', 'string', 'max:160'],
            'collateral_items.*.serial_number' => ['required', 'string', 'max:160'],
            'collateral_items.*.color' => ['required', 'string', 'max:100'],
            'collateral_items.*.estimated_value' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'collateral_ownership_acknowledged' => ['nullable', 'accepted'],
            'security_declaration_initials' => ['nullable', 'string', 'max:5', 'regex:/^[A-Za-z]{1,5}$/'],
            'documents' => ['nullable', 'array'],
            'documents.national_id' => $requiredDocument,
            'documents.appointment_letter' => $requiredDocument,
            'documents.passport_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:25600'],
            'documents.payslips' => $requiredDocument,
            'documents.bank_statement' => $requiredDocument,
            'documents.mpesa_statement' => $requiredDocument,
            'documents.guarantor_1_id' => $requiredDocument,
            'documents.guarantor_2_id' => $requiredDocument,
            'documents.guarantor_1_confirmation' => $requiredDocument,
            'documents.guarantor_2_confirmation' => $requiredDocument,
            'residence_visit_consent' => ['accepted'],
            'borrower_debt_acknowledged' => ['accepted'],
            'borrower_salary_remedy_acknowledged' => ['accepted'],
            'borrower_continued_interest_acknowledged' => ['accepted'],
            'borrower_processing_fee_acknowledged' => ['accepted'],
            'borrower_late_penalty_acknowledged' => ['accepted'],
            'borrower_signature' => ['required', 'string', 'max:160'],
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
        $collateralItems = $this->input('collateral_items', []);
        $items = collect(is_array($collateralItems) ? $collateralItems : [])
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['description'] ?? null))
            ->values()
            ->all();

        $residenceDetails = $this->input('residence_details', []);
        $residenceDetails = is_array($residenceDetails) ? $residenceDetails : [];
        $employmentDetails = $this->input('employment_details', []);
        $employmentDetails = is_array($employmentDetails) ? $employmentDetails : [];

        $this->merge([
            'collateral_items' => $items,
            'residence' => implode(', ', array_filter([
                $residenceDetails['house_number'] ?? null,
                $residenceDetails['building_name'] ?? null,
                $residenceDetails['estate'] ?? null,
                $residenceDetails['city_town'] ?? null,
            ], fn (mixed $value): bool => is_string($value) && filled($value))),
            'employer' => $employmentDetails['employer_name'] ?? null,
            'employer_location' => $employmentDetails['location_branch'] ?? null,
            'job_title' => $employmentDetails['specific_title'] ?? null,
            'net_salary' => $employmentDetails['net_salary'] ?? null,
        ]);
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

            $guarantors = $this->input('guarantors', []);
            $isColleagueIncluded = is_array($guarantors) && count(array_filter($guarantors, fn (mixed $guarantor): bool => is_array($guarantor) && filter_var($guarantor['is_colleague'] ?? false, FILTER_VALIDATE_BOOLEAN))) > 0;

            if (! $isColleagueIncluded) {
                $validator->errors()->add('guarantors', 'At least one of the two guarantors must be your colleague.');
            }

            if (is_string($this->input('borrower_signature')) && is_string($this->input('full_name')) && filled($this->input('borrower_signature')) && Str::lower(trim($this->input('borrower_signature'))) !== Str::lower(trim($this->input('full_name')))) {
                $validator->errors()->add('borrower_signature', 'Type your full name exactly as it appears in your borrower information.');
            }

            foreach (is_array($guarantors) ? $guarantors : [] as $index => $guarantor) {
                if (is_array($guarantor) && isset($guarantor['signature_name'], $guarantor['full_name']) && is_string($guarantor['signature_name']) && is_string($guarantor['full_name']) && Str::lower(trim($guarantor['signature_name'])) !== Str::lower(trim($guarantor['full_name']))) {
                    $validator->errors()->add("guarantors.{$index}.signature_name", 'Type this guarantor’s full name exactly as entered above.');
                }
            }

            if (is_array($this->input('collateral_items')) && $this->input('collateral_items') !== [] && $this->input('collateral_ownership_acknowledged') !== '1') {
                $validator->errors()->add('collateral_ownership_acknowledged', 'Confirm that you own these items and that they are free from encumbrances.');
            }

            if (is_array($this->input('collateral_items')) && $this->input('collateral_items') !== [] && ! filled($this->input('security_declaration_initials'))) {
                $validator->errors()->add('security_declaration_initials', 'Add your initials in the Security and collateral step to sign the security declaration.');
            }

            if (is_string($this->input('requested_amount')) && is_string($this->input('requested_amount_words')) && filled($this->input('requested_amount_words')) && $this->input('requested_amount_words') !== (new KenyanMoneyWords)->spell($this->input('requested_amount'))) {
                $validator->errors()->add('requested_amount_words', 'The amount in words must match the requested loan amount.');
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
