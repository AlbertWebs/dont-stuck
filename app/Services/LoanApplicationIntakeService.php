<?php

namespace App\Services;

use App\Models\ApplicationDocument;
use App\Models\ApplicationStatusHistory;
use App\Models\Borrower;
use App\Models\CollateralItem;
use App\Models\Guarantor;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanTermsSetting;
use App\Support\KenyanMoneyWords;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoanApplicationIntakeService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly LoanRepaymentCalculator $repaymentCalculator,
        private readonly KenyanMoneyWords $moneyWords,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{application: LoanApplication, reference: string}
     */
    public function submit(array $data, Request $request): array
    {
        $application = DB::transaction(function () use ($data, $request): LoanApplication {
            $product = LoanProduct::query()->findOrFail($data['loan_product_id']);
            $terms = LoanTermsSetting::query()->findOrFail(1);
            $paymentSummary = $this->repaymentCalculator->calculate((string) $data['requested_amount'], (int) $data['requested_months'], $terms);

            $borrowerAttributes = [
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'residence' => $data['residence'] ?? null,
                'employer' => $data['employer'] ?? null,
                'employer_location' => $data['employer_location'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'net_salary' => $data['net_salary'] ?? null,
                'next_of_kin_name' => $data['next_of_kin_name'] ?? null,
                'next_of_kin_id' => $data['next_of_kin_id'] ?? null,
                'next_of_kin_relation' => $data['next_of_kin_relation'] ?? null,
                'next_of_kin_phone' => $data['next_of_kin_phone'] ?? null,
            ];

            $borrower = Borrower::query()->updateOrCreate(
                ['national_id' => $data['national_id']],
                $borrowerAttributes,
            );

            $reference = 'DSS-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
            $application = LoanApplication::create([
                'reference' => $reference,
                'borrower_id' => $borrower->id,
                'loan_product_id' => $data['loan_product_id'],
                'requested_amount' => $data['requested_amount'],
                'requested_months' => $data['requested_months'] ?? null,
                'purpose' => $data['purpose'],
                'status' => 'submitted',
                'consented_at' => now(),
                'applicant_notes' => $data['applicant_notes'] ?? null,
                'application_data' => [
                    'form_version' => 'employed_dss_v1',
                    'residence_details' => $data['residence_details'],
                    'employment_details' => $data['employment_details'],
                    'next_of_kin' => [
                        'name' => $data['next_of_kin_name'] ?? null,
                        'national_id' => $data['next_of_kin_id'] ?? null,
                        'relationship' => $data['next_of_kin_relation'] ?? null,
                        'phone' => $data['next_of_kin_phone'] ?? null,
                    ],
                    'agreement' => [
                        'borrower_signature' => $data['borrower_signature'],
                        'signed_at' => now()->toIso8601String(),
                        'requested_amount_words' => $this->moneyWords->spell((string) $data['requested_amount']),
                        'total_repayment_amount' => $paymentSummary['total_repayment'],
                        'total_repayment_words' => $paymentSummary['total_repayment_words'],
                        'interest_rate_monthly' => $paymentSummary['interest_rate'],
                        'interest_basis' => $paymentSummary['interest_basis'],
                        'processing_fee_rate' => (string) $terms->processing_fee_rate,
                        'processing_fee_amount' => $paymentSummary['processing_fee'],
                        'legal_fee_amount' => $paymentSummary['legal_fee'],
                        'net_disbursement' => $paymentSummary['net_disbursement'],
                        'daily_late_penalty' => $product->daily_late_fee ?? '200.00',
                        'late_penalty_start' => $product->late_penalty_start,
                        'debt_acknowledged' => true,
                        'salary_remedy_acknowledged' => true,
                        'continued_interest_acknowledged' => true,
                        'processing_fee_acknowledged' => true,
                        'late_penalty_acknowledged' => true,
                    ],
                    'requirements' => [
                        'home_or_workplace_visit_consent' => true,
                        'collateral_ownership_confirmed' => ! empty($data['collateral_items']),
                        'collateral_initials' => empty($data['collateral_items']) ? null : $data['security_declaration_initials'],
                        'collateral_signed_by' => empty($data['collateral_items']) ? null : $data['security_declaration_initials'],
                        'collateral_signed_at' => empty($data['collateral_items']) ? null : now()->toIso8601String(),
                    ],
                ],
            ]);

            foreach ($data['guarantors'] as $guarantorData) {
                Guarantor::create([
                    'loan_application_id' => $application->id,
                    'full_name' => $guarantorData['full_name'],
                    'national_id' => $guarantorData['national_id'],
                    'phone' => $guarantorData['phone'],
                    'relationship' => $guarantorData['relationship'] ?? null,
                    'is_colleague' => $guarantorData['is_colleague'] ?? false,
                    'email' => $guarantorData['email'] ?? null,
                    'address' => $guarantorData['address'],
                    'work_phone' => $guarantorData['work_phone'],
                    'work_station' => $guarantorData['work_station'],
                    'residence' => $guarantorData['residence'],
                    'building_name' => $guarantorData['building_name'],
                    'house_number' => $guarantorData['house_number'],
                    'guarantee_amount' => $paymentSummary['total_repayment'],
                    'guarantee_amount_words' => $paymentSummary['total_repayment_words'],
                    'signature_name' => $guarantorData['signature_name'],
                    'signed_on' => $guarantorData['signed_on'],
                    'joint_and_several_accepted' => true,
                ]);
            }

            foreach (($data['collateral_items'] ?? []) as $collateralData) {
                CollateralItem::create([
                    'loan_application_id' => $application->id,
                    'description' => $collateralData['description'],
                    'quantity' => $collateralData['quantity'],
                    'make_model' => $collateralData['make_model'] ?? null,
                    'serial_number' => $collateralData['serial_number'] ?? null,
                    'color' => $collateralData['color'],
                    'estimated_value' => $collateralData['estimated_value'] ?? null,
                    'ownership_confirmed' => true,
                ]);
            }

            ApplicationStatusHistory::create([
                'loan_application_id' => $application->id,
                'from_status' => null,
                'to_status' => 'submitted',
                'remarks' => 'Application received through the public website.',
            ]);

            foreach (($data['documents'] ?? []) as $type => $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $path = $file->store('loan-applications/'.$application->reference, 'local');
                ApplicationDocument::create([
                    'loan_application_id' => $application->id,
                    'document_type' => $type,
                    'original_name' => $file->getClientOriginalName(),
                    'storage_path' => $path,
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'size_bytes' => $file->getSize() ?: 0,
                ]);
            }

            $this->auditLogger->record(null, 'application.submitted', $application, [
                'reference' => $reference,
                'form_version' => 'employed_dss_v1',
                'supporting_document_count' => count($data['documents'] ?? []),
                'borrower_signature_captured' => true,
                'guarantor_count' => count($data['guarantors']),
                'collateral_item_count' => count($data['collateral_items'] ?? []),
            ], $request);

            return $application;
        });

        return ['application' => $application, 'reference' => $application->reference];
    }
}
