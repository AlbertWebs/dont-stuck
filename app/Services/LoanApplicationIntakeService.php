<?php

namespace App\Services;

use App\Models\ApplicationDocument;
use App\Models\ApplicationStatusHistory;
use App\Models\Borrower;
use App\Models\CollateralItem;
use App\Models\Guarantor;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoanApplicationIntakeService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{application: LoanApplication, reference: string}
     */
    public function submit(array $data, Request $request): array
    {
        $application = DB::transaction(function () use ($data, $request): LoanApplication {
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
                    'employer' => $data['employer'] ?? null,
                    'employer_location' => $data['employer_location'] ?? null,
                    'job_title' => $data['job_title'] ?? null,
                    'net_salary' => $data['net_salary'] ?? null,
                    'residence' => $data['residence'] ?? null,
                    'next_of_kin' => [
                        'name' => $data['next_of_kin_name'] ?? null,
                        'national_id' => $data['next_of_kin_id'] ?? null,
                        'relationship' => $data['next_of_kin_relation'] ?? null,
                        'phone' => $data['next_of_kin_phone'] ?? null,
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
                ]);
            }

            foreach (($data['collateral_items'] ?? []) as $collateralData) {
                CollateralItem::create([
                    'loan_application_id' => $application->id,
                    'description' => $collateralData['description'],
                    'quantity' => $collateralData['quantity'],
                    'make_model' => $collateralData['make_model'] ?? null,
                    'serial_number' => $collateralData['serial_number'] ?? null,
                    'estimated_value' => $collateralData['estimated_value'] ?? null,
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

            $this->auditLogger->record(null, 'application.submitted', $application, ['reference' => $reference], $request);

            return $application;
        });

        return ['application' => $application, 'reference' => $application->reference];
    }
}
