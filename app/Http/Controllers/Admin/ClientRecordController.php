<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\Borrower;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Services\AuditLogger;
use App\Services\ClientRecordPdfExporter;
use App\Services\ExcelXmlExporter;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientRecordController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeStaff($request);
        $borrowers = $this->visibleBorrowers($request)
            ->withCount(['applications', 'loanAccounts'])
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('full_name', 'like', $term)
                        ->orWhere('national_id', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.clients.index', ['borrowers' => $borrowers]);
    }

    public function show(Request $request, Borrower $borrower): View
    {
        $this->authorizeBorrower($request, $borrower);
        $borrower->load([
            'applications.product',
            'applications.assignedOfficer',
            'applications.statusHistory.actor',
            'applications.reviews.reviewer',
            'applications.documents.uploadedBy',
            'applications.guarantors',
            'applications.collateralItems',
            'applications.loanAccount.schedules',
            'applications.loanAccount.payments.recorder',
            'applications.loanAccount.payments.reversedBy',
            'applications.loanAccount.payments.allocations.schedule',
            'applications.loanAccount.disbursements.recorder',
            'applications.loanAccount.charges.recorder',
            'loanAccounts.application.product',
            'loanAccounts.schedules',
            'loanAccounts.payments.recorder',
            'loanAccounts.payments.reversedBy',
            'loanAccounts.payments.allocations.schedule',
            'loanAccounts.disbursements.recorder',
            'loanAccounts.charges.recorder',
        ]);

        $applicationIds = $borrower->applications->modelKeys();
        $documentIds = $borrower->applications->flatMap->documents->pluck('id')->all();
        $loanIds = $borrower->loanAccounts->modelKeys();
        $paymentIds = $borrower->loanAccounts->flatMap->payments->pluck('id')->all();
        $activity = AuditLog::query()
            ->with('actor')
            ->where(function (Builder $query) use ($borrower, $applicationIds, $documentIds, $loanIds, $paymentIds): void {
                $query->where(function (Builder $query) use ($borrower): void {
                    $query->where('subject_type', Borrower::class)->where('subject_id', $borrower->id);
                });

                foreach ([LoanApplication::class => $applicationIds, ApplicationDocument::class => $documentIds, LoanAccount::class => $loanIds, Payment::class => $paymentIds] as $type => $ids) {
                    if ($ids !== []) {
                        $query->orWhere(function (Builder $query) use ($type, $ids): void {
                            $query->where('subject_type', $type)->whereIn('subject_id', $ids);
                        });
                    }
                }
            })
            ->latest()
            ->get();

        $applications = $borrower->applications;
        $loanAccounts = $borrower->loanAccounts;
        $schedules = $loanAccounts->flatMap->schedules;
        $payments = $loanAccounts->flatMap->payments;

        return view('admin.clients.show', [
            'borrower' => $borrower,
            'applications' => $applications,
            'loanAccounts' => $loanAccounts,
            'schedules' => $schedules,
            'payments' => $payments,
            'activity' => $activity,
            'totals' => [
                'disbursed' => Money::fromCents($loanAccounts->sum(fn (LoanAccount $loan): int => Money::toCents((string) $loan->disbursed_amount))),
                'paid' => Money::fromCents($payments->where('status', 'posted')->sum(fn (Payment $payment): int => Money::toCents((string) $payment->amount))),
                'outstanding' => Money::fromCents($loanAccounts->sum(fn (LoanAccount $loan): int => Money::toCents($loan->outstandingBalance()))),
            ],
        ]);
    }

    public function export(Request $request, Borrower $borrower, ExcelXmlExporter $exporter, AuditLogger $auditLogger): StreamedResponse
    {
        $this->authorizeBorrower($request, $borrower);
        $sheets = $this->clientRecordSheets($request, $borrower, $auditLogger, 'excel_xml_workbook');

        return $exporter->downloadWorkbook('client-record-'.str($borrower->full_name)->slug().'-'.today()->toDateString().'.xls', $sheets);
    }

    public function pdf(Request $request, Borrower $borrower, ClientRecordPdfExporter $exporter, AuditLogger $auditLogger): StreamedResponse
    {
        $this->authorizeBorrower($request, $borrower);
        $sheets = $this->clientRecordSheets($request, $borrower, $auditLogger, 'pdf');

        return $exporter->download(
            'client-record-'.str($borrower->full_name)->slug().'-'.today()->toDateString().'.pdf',
            $borrower->full_name,
            (string) $borrower->national_id,
            (string) $borrower->phone,
            $sheets,
        );
    }

    /**
     * @return array<string, array{headers: array<int, string>, rows: Collection<int, array<int, int|float|string|null>>}>
     */
    private function clientRecordSheets(Request $request, Borrower $borrower, AuditLogger $auditLogger, string $format): array
    {
        $borrower->load([
            'applications.product',
            'applications.assignedOfficer',
            'applications.statusHistory.actor',
            'applications.reviews.reviewer',
            'applications.documents.uploadedBy',
            'applications.guarantors',
            'applications.collateralItems',
            'applications.loanAccount.schedules',
            'applications.loanAccount.payments.recorder',
            'applications.loanAccount.payments.reversedBy',
            'applications.loanAccount.payments.allocations.schedule',
            'applications.loanAccount.disbursements.recorder',
            'applications.loanAccount.charges.recorder',
            'loanAccounts.application.product',
            'loanAccounts.schedules',
            'loanAccounts.payments.recorder',
            'loanAccounts.payments.reversedBy',
            'loanAccounts.payments.allocations.schedule',
            'loanAccounts.disbursements.recorder',
            'loanAccounts.charges.recorder',
        ]);

        $applications = $borrower->applications;
        $loanAccounts = $borrower->loanAccounts;
        $applicationIds = $applications->modelKeys();
        $documentIds = $applications->flatMap->documents->pluck('id')->all();
        $loanIds = $loanAccounts->modelKeys();
        $paymentIds = $loanAccounts->flatMap->payments->pluck('id')->all();
        $auditLogger->record($request->user(), 'client_record.exported', $borrower, [
            'application_count' => $applications->count(),
            'loan_count' => $loanAccounts->count(),
            'format' => $format,
        ], $request);

        $activity = AuditLog::query()
            ->with('actor')
            ->where(function (Builder $query) use ($borrower, $applicationIds, $documentIds, $loanIds, $paymentIds): void {
                $query->where(fn (Builder $query) => $query->where('subject_type', Borrower::class)->where('subject_id', $borrower->id));
                foreach ([LoanApplication::class => $applicationIds, ApplicationDocument::class => $documentIds, LoanAccount::class => $loanIds, Payment::class => $paymentIds] as $type => $ids) {
                    if ($ids !== []) {
                        $query->orWhere(fn (Builder $query) => $query->where('subject_type', $type)->whereIn('subject_id', $ids));
                    }
                }
            })
            ->oldest()
            ->get();

        return [
            'Client profile' => [
                'headers' => ['Field', 'Information'],
                'rows' => collect([
                    ['Full name', $borrower->full_name], ['National ID', $borrower->national_id], ['Phone', $borrower->phone],
                    ['Email', $borrower->email], ['Residence', $borrower->residence], ['Employer', $borrower->employer],
                    ['Employer location', $borrower->employer_location], ['Job title', $borrower->job_title],
                    ['Net monthly salary (KSh)', $borrower->net_salary], ['Next of kin', $borrower->next_of_kin_name],
                    ['Next of kin ID', $borrower->next_of_kin_id], ['Next of kin relationship', $borrower->next_of_kin_relation],
                    ['Next of kin phone', $borrower->next_of_kin_phone], ['Client record created', $borrower->created_at?->toDateTimeString()],
                ]),
            ],
            'Applications' => ['headers' => ['Reference', 'Product', 'Requested amount (KSh)', 'Requested months', 'Approved amount (KSh)', 'Approved months', 'Status', 'Purpose', 'Residence details', 'Employment details', 'Applicant notes', 'Decision notes', 'Assigned officer', 'Submitted'], 'rows' => $applications->map(fn ($application): array => [$application->reference, $application->product->name, $application->requested_amount, $application->requested_months, $application->approved_amount, $application->approved_months, $application->status, $application->purpose, json_encode($application->application_data['residence_details'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), json_encode($application->application_data['employment_details'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $application->applicant_notes, $application->decision_notes, $application->assignedOfficer?->name, $application->created_at?->toDateTimeString()])],
            'Borrower agreements' => ['headers' => ['Application reference', 'Signed by', 'Signed at', 'Amount requested in words', 'Total repayment (KSh)', 'Total repayment in words', 'Interest rate monthly', 'Interest method', 'Processing fee rate', 'Processing fee (KSh)', 'Legal fee (KSh)', 'Net payout (KSh)', 'Late penalty per day (KSh)', 'Debt agreed', 'Salary remedy agreed', 'Continued interest agreed', 'Fees agreed', 'Late penalty agreed'], 'rows' => $applications->filter(fn ($application): bool => ! empty($application->application_data['agreement']))->map(function ($application): array {
                $agreement = $application->application_data['agreement'];

                return [$application->reference, $agreement['borrower_signature'] ?? null, $agreement['signed_at'] ?? null, $agreement['requested_amount_words'] ?? null, $agreement['total_repayment_amount'] ?? null, $agreement['total_repayment_words'] ?? null, $agreement['interest_rate_monthly'] ?? null, $agreement['interest_basis'] ?? null, $agreement['processing_fee_rate'] ?? null, $agreement['processing_fee_amount'] ?? null, $agreement['legal_fee_amount'] ?? null, $agreement['net_disbursement'] ?? null, $agreement['daily_late_penalty'] ?? null, ! empty($agreement['debt_acknowledged']) ? 'Yes' : 'No', ! empty($agreement['salary_remedy_acknowledged']) ? 'Yes' : 'No', ! empty($agreement['continued_interest_acknowledged']) ? 'Yes' : 'No', ! empty($agreement['processing_fee_acknowledged']) ? 'Yes' : 'No', ! empty($agreement['late_penalty_acknowledged']) ? 'Yes' : 'No'];
            })],
            'Application history' => ['headers' => ['Application reference', 'Record type', 'Date', 'From status', 'To status / recommendation', 'Officer', 'Details'], 'rows' => $this->applicationHistoryRows($applications)],
            'Loans' => ['headers' => ['Loan reference', 'Application reference', 'Status', 'Principal (KSh)', 'Net disbursed (KSh)', 'Interest rate % monthly', 'Interest method', 'Processing fee (KSh)', 'Legal fee (KSh)', 'Term months', 'Disbursed', 'Maturity', 'Outstanding (KSh)'], 'rows' => $loanAccounts->map(fn (LoanAccount $loan): array => [$loan->reference, $loan->application->reference, $loan->status, $loan->principal_amount, $loan->disbursed_amount, $loan->interest_rate, $loan->interest_basis, $loan->processing_fee, $loan->commissioning_fee, $loan->term_months, $loan->disbursed_at?->toDateString(), $loan->maturity_date?->toDateString(), $loan->outstandingBalance()])],
            'Installments' => ['headers' => ['Loan reference', 'Installment', 'Due date', 'Principal due', 'Interest due', 'Fees due', 'Penalties due', 'Principal paid', 'Interest paid', 'Fees paid', 'Penalties paid', 'Total paid', 'Outstanding', 'Status'], 'rows' => $loanAccounts->flatMap(fn (LoanAccount $loan) => $loan->schedules->sortBy('installment_number')->map(fn ($schedule): array => [$loan->reference, $schedule->installment_number, $schedule->due_date->toDateString(), $schedule->principal_due, $schedule->interest_due, $schedule->fees_due, $schedule->penalties_due, $schedule->principal_paid, $schedule->interest_paid, $schedule->fees_paid, $schedule->penalties_paid, $schedule->paidAmount(), $schedule->outstandingAmount(), $schedule->status]))],
            'Payments' => ['headers' => ['Loan reference', 'Date', 'Amount (KSh)', 'Method', 'External reference', 'Status', 'Recorded by', 'Reversed by', 'Reversed at', 'Reversal reason', 'Notes'], 'rows' => $loanAccounts->flatMap(fn (LoanAccount $loan) => $loan->payments->sortBy('paid_on')->map(fn ($payment): array => [$loan->reference, $payment->paid_on->toDateString(), $payment->amount, $payment->method, $payment->external_reference, $payment->status, $payment->recorder?->name, $payment->reversedBy?->name, $payment->reversed_at?->toDateTimeString(), $payment->reversal_reason, $payment->notes]))],
            'Payment allocations' => ['headers' => ['Loan reference', 'Payment date', 'Payment status', 'Installment', 'Principal', 'Interest', 'Fees', 'Penalties'], 'rows' => $loanAccounts->flatMap(fn (LoanAccount $loan) => $loan->payments->flatMap(fn ($payment) => $payment->allocations->map(fn ($allocation): array => [$loan->reference, $payment->paid_on->toDateString(), $payment->status, $allocation->schedule->installment_number, $allocation->principal_amount, $allocation->interest_amount, $allocation->fees_amount, $allocation->penalty_amount])))],
            'Disbursements & charges' => ['headers' => ['Loan reference', 'Record type', 'Date', 'Amount (KSh)', 'Gross amount (KSh)', 'Fees withheld (KSh)', 'Method / charge type', 'Status', 'Recorded by', 'Reference / notes'], 'rows' => $this->financialEventRows($loanAccounts)],
            'Guarantors' => ['headers' => ['Application reference', 'Name', 'National ID', 'Phone', 'Email', 'Relationship', 'Colleague', 'Address', 'Work phone', 'Work station', 'Residence', 'Building', 'House', 'Guarantee (KSh)', 'Guarantee in words', 'Signature name', 'Signed on', 'Joint & several accepted'], 'rows' => $this->guarantorRows($applications)],
            'Collateral' => ['headers' => ['Application reference', 'Description', 'Quantity', 'Make / model', 'Serial number', 'Colour', 'Estimated value (KSh)', 'Ownership declaration confirmed', 'Security declaration sign-off', 'Declaration recorded at'], 'rows' => $this->collateralRows($applications)],
            'Supporting records' => ['headers' => ['Application reference', 'Record type', 'Name / description', 'Identifier / details', 'Phone / quantity', 'Relationship / type', 'Value (KSh)', 'Date / uploaded by'], 'rows' => $this->supportingRows($applications)],
            'Activity log' => ['headers' => ['Date', 'Action', 'Related record', 'Staff member', 'Details'], 'rows' => $activity->map(fn (AuditLog $log): array => [$log->created_at?->toDateTimeString(), $log->action, class_basename((string) $log->subject_type).' #'.$log->subject_id, $log->actor?->name ?? 'Online / system', json_encode($log->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''])],
        ];
    }

    /** @param Collection<int, LoanApplication> $applications
     * @return Collection<int, array<int, int|float|string|null>>
     */
    private function applicationHistoryRows(Collection $applications): Collection
    {
        return $applications->flatMap(function ($application): Collection {
            $statusRows = $application->statusHistory->map(fn ($history): array => [$application->reference, 'Status change', $history->created_at?->toDateTimeString(), $history->from_status, $history->to_status, $history->actor?->name ?? 'Online / system', $history->remarks]);
            $reviewRows = $application->reviews->map(fn ($review): array => [$application->reference, 'Review', $review->created_at?->toDateTimeString(), null, $review->recommendation, $review->reviewer->name, $review->notes]);

            return $statusRows->concat($reviewRows);
        });
    }

    /** @param Collection<int, LoanAccount> $loanAccounts
     * @return Collection<int, array<int, int|float|string|null>>
     */
    private function financialEventRows(Collection $loanAccounts): Collection
    {
        return $loanAccounts->flatMap(function (LoanAccount $loan): Collection {
            $disbursements = $loan->disbursements->map(fn ($record): array => [$loan->reference, 'Disbursement', $record->disbursed_on->toDateString(), $record->amount, $record->gross_amount, $record->fees_withheld, $record->method, 'confirmed', $record->recorder?->name, trim(($record->provider_reference ?? '').' '.$record->notes)]);
            $charges = $loan->charges->map(fn ($record): array => [$loan->reference, 'Charge', $record->charged_on->toDateString(), $record->amount, null, null, $record->charge_type, $record->status, $record->recorder?->name, $record->notes]);

            return $disbursements->concat($charges);
        });
    }

    /** @param Collection<int, LoanApplication> $applications
     * @return Collection<int, array<int, int|float|string|null>>
     */
    private function supportingRows(Collection $applications): Collection
    {
        return $applications->flatMap(function (LoanApplication $application): Collection {
            return $application->documents->map(fn ($document): array => [$application->reference, 'Document metadata', $document->original_name, $document->mime_type.' · '.number_format($document->size_bytes).' bytes', null, $document->document_type, null, $document->created_at?->toDateTimeString()]);
        });
    }

    /** @param Collection<int, LoanApplication> $applications
     * @return Collection<int, array<int, int|float|string|null>>
     */
    private function guarantorRows(Collection $applications): Collection
    {
        return $applications->flatMap(fn (LoanApplication $application) => $application->guarantors->map(fn ($guarantor): array => [$application->reference, $guarantor->full_name, $guarantor->national_id, $guarantor->phone, $guarantor->email, $guarantor->relationship, $guarantor->is_colleague ? 'Yes' : 'No', $guarantor->address, $guarantor->work_phone, $guarantor->work_station, $guarantor->residence, $guarantor->building_name, $guarantor->house_number, $guarantor->guarantee_amount, $guarantor->guarantee_amount_words, $guarantor->signature_name, $guarantor->signed_on?->toDateString(), $guarantor->joint_and_several_accepted ? 'Yes' : 'No']));
    }

    /** @param Collection<int, LoanApplication> $applications
     * @return Collection<int, array<int, int|float|string|null>>
     */
    private function collateralRows(Collection $applications): Collection
    {
        return $applications->flatMap(function (LoanApplication $application): Collection {
            $requirements = $application->application_data['requirements'] ?? [];

            return $application->collateralItems->map(fn ($item): array => [$application->reference, $item->description, $item->quantity, $item->make_model, $item->serial_number, $item->color, $item->estimated_value, $item->ownership_confirmed ? 'Yes' : 'No', $requirements['collateral_initials'] ?? $requirements['collateral_signed_by'] ?? null, $requirements['collateral_signed_at'] ?? null]);
        });
    }

    private function authorizeStaff(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['administrator', 'loan_officer', 'finance_officer', 'management']), 403);
    }

    private function authorizeBorrower(Request $request, Borrower $borrower): void
    {
        $this->authorizeStaff($request);
        $user = $request->user();

        if ($user->role === 'loan_officer') {
            abort_unless($borrower->applications()->where('assigned_user_id', $user->id)->exists(), 403);
        } elseif ($user->role === 'finance_officer') {
            abort_unless($borrower->loanAccounts()->exists(), 403);
        }
    }

    /** @return Builder<Borrower> */
    private function visibleBorrowers(Request $request): Builder
    {
        return Borrower::query()->when($request->user()->role === 'loan_officer', fn (Builder $query) => $query->whereHas('applications', fn (Builder $applications) => $applications->where('assigned_user_id', $request->user()->id)))
            ->when($request->user()->role === 'finance_officer', fn (Builder $query) => $query->whereHas('loanAccounts'));
    }
}
