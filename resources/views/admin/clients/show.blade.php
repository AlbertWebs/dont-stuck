@extends('layouts.admin')

@section('title', 'Client record')
@section('content')
<div class="detail-back"><a href="{{ route('admin.clients.index') }}">← Client records</a><span>·</span><span>{{ $borrower->national_id }}</span></div>
<div class="detail-heading client-record-heading"><div><span class="eyebrow">Complete borrower file</span><h1>{{ $borrower->full_name }}</h1><p>{{ $borrower->phone }} · {{ $borrower->email ?: 'Email not provided' }} · Client since {{ $borrower->created_at->format('d M Y') }}</p></div><a class="button button-dark" href="{{ route('admin.clients.export', $borrower) }}"><span aria-hidden="true">⇩</span> Export bank workbook</a></div>

<div class="client-record-kpis">
    <article class="kpi-card"><div class="kpi-top"><span>Applications</span><span class="kpi-icon kpi-blue">▤</span></div><strong>{{ $applications->count() }}</strong><small>All submitted requests</small></article>
    <article class="kpi-card"><div class="kpi-top"><span>Loan accounts</span><span class="kpi-icon kpi-green">◉</span></div><strong>{{ $loanAccounts->count() }}</strong><small>Disbursed and open accounts</small></article>
    <article class="kpi-card"><div class="kpi-top"><span>Total disbursed</span><span class="kpi-icon kpi-amber">↗</span></div><strong>KSh {{ number_format((float) $totals['disbursed'], 2) }}</strong><small>Net payout to client</small></article>
    <article class="kpi-card"><div class="kpi-top"><span>Repayments posted</span><span class="kpi-icon kpi-green">↓</span></div><strong>KSh {{ number_format((float) $totals['paid'], 2) }}</strong><small>Confirmed receipts</small></article>
    <article class="kpi-card"><div class="kpi-top"><span>Outstanding</span><span class="kpi-icon kpi-violet">◷</span></div><strong>KSh {{ number_format((float) $totals['outstanding'], 2) }}</strong><small>Across all loan schedules</small></article>
</div>

<div class="client-record-grid">
    <section class="panel client-profile-panel"><div class="panel-heading"><div><h2>Client information</h2><p>Identity, contact, employment and next of kin</p></div></div><div class="client-profile-grid">
        <div><small>NATIONAL ID</small><strong>{{ $borrower->national_id }}</strong></div><div><small>PHONE</small><strong>{{ $borrower->phone }}</strong></div><div><small>EMAIL</small><strong>{{ $borrower->email ?: 'Not provided' }}</strong></div><div><small>RESIDENCE</small><strong>{{ $borrower->residence ?: 'Not provided' }}</strong></div>
        <div><small>EMPLOYER</small><strong>{{ $borrower->employer ?: 'Not provided' }}</strong></div><div><small>EMPLOYER LOCATION</small><strong>{{ $borrower->employer_location ?: 'Not provided' }}</strong></div><div><small>JOB TITLE</small><strong>{{ $borrower->job_title ?: 'Not provided' }}</strong></div><div><small>NET MONTHLY SALARY</small><strong>{{ $borrower->net_salary ? 'KSh '.number_format((float) $borrower->net_salary, 2) : 'Not provided' }}</strong></div>
        <div><small>NEXT OF KIN</small><strong>{{ $borrower->next_of_kin_name ?: 'Not provided' }}</strong></div><div><small>NEXT OF KIN ID</small><strong>{{ $borrower->next_of_kin_id ?: 'Not provided' }}</strong></div><div><small>RELATIONSHIP</small><strong>{{ $borrower->next_of_kin_relation ?: 'Not provided' }}</strong></div><div><small>NEXT OF KIN PHONE</small><strong>{{ $borrower->next_of_kin_phone ?: 'Not provided' }}</strong></div>
    </div></section>

    <section class="panel client-applications-panel"><div class="panel-heading"><div><h2>Applications & decisions</h2><p>Requests, approvals and review history</p></div></div>
        @forelse ($applications as $application)
            <article class="client-application-card"><div class="client-record-row-heading"><div><a href="{{ route('admin.applications.show', $application) }}"><strong>{{ $application->reference }}</strong></a><small>{{ $application->product->name }} · Submitted {{ $application->created_at->format('d M Y') }}</small></div><span class="status-badge status-{{ $application->status }}">{{ str_replace('_', ' ', ucfirst($application->status)) }}</span></div>
                <div class="client-application-facts"><span>Requested<strong>KSh {{ number_format((float) $application->requested_amount, 2) }} · {{ $application->requested_months ?: '—' }} mo.</strong></span><span>Approved<strong>{{ $application->approved_amount ? 'KSh '.number_format((float) $application->approved_amount, 2).' · '.$application->approved_months.' mo.' : 'No approval recorded' }}</strong></span><span>Officer<strong>{{ $application->assignedOfficer?->name ?? 'Unassigned' }}</strong></span></div>
                @if ($application->decision_notes)<p class="client-record-note"><strong>Decision notes:</strong> {{ $application->decision_notes }}</p>@endif
                @if (! empty($application->application_data['agreement']))
                    @php($agreement = $application->application_data['agreement'])
                    <details class="client-history-details"><summary>Borrower agreement and signed terms</summary><div class="client-history-list"><article><strong>Signed by {{ $agreement['borrower_signature'] }} · {{ \Illuminate\Support\Carbon::parse($agreement['signed_at'])->format('d M Y · H:i') }}</strong><p>Requested: {{ $agreement['requested_amount_words'] }}. Total repayment KSh {{ number_format((float) $agreement['total_repayment_amount'], 2) }} ({{ $agreement['total_repayment_words'] }}). Interest {{ $agreement['interest_rate_monthly'] }}% monthly · {{ str_replace('_', ' ', $agreement['interest_basis']) }}. Processing fee {{ $agreement['processing_fee_rate'] }}% (KSh {{ number_format((float) $agreement['processing_fee_amount'], 2) }}); legal fee KSh {{ number_format((float) $agreement['legal_fee_amount'], 2) }}. Late penalty KSh {{ number_format((float) $agreement['daily_late_penalty'], 2) }} per day.</p><small>Debt, salary recovery terms, continuing interest, fees and late penalty acknowledged.</small></article></div></details>
                @endif
                @foreach ($application->guarantors as $guarantor)
                    <details class="client-history-details"><summary>Guarantor {{ $loop->iteration }} · {{ $guarantor->full_name }} · {{ $guarantor->is_colleague ? 'Colleague' : 'Not marked as colleague' }}</summary><div class="client-application-facts"><span>Identity<strong>{{ $guarantor->national_id }} · {{ $guarantor->phone }} · {{ $guarantor->email ?: 'No email' }}</strong></span><span>Relationship & address<strong>{{ $guarantor->relationship }} · {{ $guarantor->address }} · {{ $guarantor->residence }}, {{ $guarantor->building_name }}, {{ $guarantor->house_number }}</strong></span><span>Work<strong>{{ $guarantor->work_phone }} · {{ $guarantor->work_station }}</strong></span><span>Guarantee<strong>KSh {{ number_format((float) $guarantor->guarantee_amount, 2) }} · {{ $guarantor->guarantee_amount_words }}</strong></span><span>Signed<strong>{{ $guarantor->signature_name }} · {{ $guarantor->signed_on?->format('d M Y') }}</strong></span><span>Joint & several liability<strong>{{ $guarantor->joint_and_several_accepted ? 'Accepted' : 'Not recorded' }}</strong></span></div></details>
                @endforeach
                @foreach ($application->collateralItems as $item)
                    <p class="client-record-note"><strong>Security item:</strong> {{ $item->description }} · {{ $item->quantity }} item(s) · {{ $item->make_model }} · Serial {{ $item->serial_number }} · Colour {{ $item->color }} · Est. KSh {{ number_format((float) $item->estimated_value, 2) }} · Ownership declaration {{ $item->ownership_confirmed ? 'confirmed' : 'not recorded' }}</p>
                @endforeach
                @if ($application->statusHistory->isNotEmpty() || $application->reviews->isNotEmpty())<details class="client-history-details"><summary>View decision and review history ({{ $application->statusHistory->count() + $application->reviews->count() }})</summary><div class="client-history-list">
                    @foreach ($application->statusHistory->sortBy('created_at') as $history)<article><strong>{{ $history->from_status ? str_replace('_', ' ', ucfirst($history->from_status)).' → ' : '' }}{{ str_replace('_', ' ', ucfirst($history->to_status)) }}</strong><small>{{ $history->created_at->format('d M Y · H:i') }} · {{ $history->actor?->name ?? 'Online / system' }}</small>@if ($history->remarks)<p>{{ $history->remarks }}</p>@endif</article>@endforeach
                    @foreach ($application->reviews->sortBy('created_at') as $review)<article><strong>Review · {{ $review->recommendation ? str_replace('_', ' ', $review->recommendation) : 'Note' }}</strong><small>{{ $review->created_at->format('d M Y · H:i') }} · {{ $review->reviewer->name }}</small><p>{{ $review->notes }}</p></article>@endforeach
                </div></details>@endif
            </article>
        @empty
            <div class="empty-table">No applications are linked to this client.</div>
        @endforelse
    </section>

    <section class="panel client-loans-panel"><div class="panel-heading"><div><h2>Loans & installments</h2><p>Account terms, repayment status and balances</p></div></div>
        @forelse ($loanAccounts as $loan)
            <article class="client-loan-card"><div class="client-record-row-heading"><div><strong>{{ $loan->reference }}</strong><small>{{ $loan->application->product->name }} · {{ $loan->term_months }} months · Matures {{ $loan->maturity_date?->format('d M Y') ?? 'Not set' }}</small></div><span class="status-badge status-{{ $loan->status }}">{{ str_replace('_', ' ', ucfirst($loan->status)) }}</span></div>
                <div class="client-loan-facts"><span>Principal<strong>KSh {{ number_format((float) $loan->principal_amount, 2) }}</strong></span><span>Net payout<strong>KSh {{ number_format((float) $loan->disbursed_amount, 2) }}</strong></span><span>Outstanding<strong>KSh {{ number_format((float) $loan->outstandingBalance(), 2) }}</strong></span><span>Interest<strong>{{ number_format((float) $loan->interest_rate, 2) }}% monthly · {{ str_replace('_', ' ', $loan->interest_basis) }}</strong></span></div>
                <div class="table-wrap"><table class="data-table"><thead><tr><th>INSTALLMENT</th><th>DUE DATE</th><th>PRINCIPAL</th><th>INTEREST</th><th>PAID</th><th>REMAINING</th><th>STATUS</th></tr></thead><tbody>
                    @forelse ($loan->schedules->sortBy('installment_number') as $schedule)<tr><td>{{ $schedule->installment_number }}</td><td>{{ $schedule->due_date->format('d M Y') }}</td><td>KSh {{ number_format((float) $schedule->principal_due, 2) }}</td><td>KSh {{ number_format((float) $schedule->interest_due, 2) }}</td><td>KSh {{ number_format((float) $schedule->paidAmount(), 2) }}</td><td>KSh {{ number_format((float) $schedule->outstandingAmount(), 2) }}</td><td><span class="status-badge status-{{ $schedule->status }}">{{ ucfirst($schedule->status) }}</span></td></tr>@empty<tr><td colspan="7">No installments scheduled.</td></tr>@endforelse
                </tbody></table></div>
            </article>
        @empty
            <div class="empty-table">No loan accounts have been disbursed to this client.</div>
        @endforelse
    </section>

    <section class="panel client-payments-panel"><div class="panel-heading"><div><h2>Financial history</h2><p>Disbursements, receipts, allocations and charges</p></div></div>
        @forelse ($loanAccounts as $loan)
            <div class="client-finance-group"><h3>{{ $loan->reference }} · {{ $loan->status }}</h3>
                @foreach ($loan->disbursements as $disbursement)<article class="client-finance-entry"><div><strong>Disbursement · KSh {{ number_format((float) $disbursement->amount, 2) }}</strong><small>{{ $disbursement->disbursed_on->format('d M Y') }} · {{ str_replace('_', ' ', $disbursement->method) }} · Recorded by {{ $disbursement->recorder?->name ?? 'Staff' }}</small></div><span>Gross KSh {{ number_format((float) $disbursement->gross_amount, 2) }} · Fees withheld KSh {{ number_format((float) $disbursement->fees_withheld, 2) }}</span></article>@endforeach
                @forelse ($loan->payments->sortByDesc('paid_on') as $payment)<article class="client-finance-entry"><div><strong>Payment · KSh {{ number_format((float) $payment->amount, 2) }} · {{ ucfirst($payment->status) }}</strong><small>{{ $payment->paid_on->format('d M Y') }} · {{ str_replace('_', ' ', $payment->method) }} · {{ $payment->external_reference ?: 'No external reference' }} · Recorded by {{ $payment->recorder?->name ?? 'Staff' }}</small>@if ($payment->reversal_reason)<small>Reversal: {{ $payment->reversal_reason }} · {{ $payment->reversedBy?->name ?? 'Staff' }} · {{ $payment->reversed_at?->format('d M Y H:i') }}</small>@endif
                    @foreach ($payment->allocations as $allocation)<small class="client-allocation-detail">Installment {{ $allocation->schedule->installment_number }}: principal KSh {{ number_format((float) $allocation->principal_amount, 2) }} · interest KSh {{ number_format((float) $allocation->interest_amount, 2) }} · fees KSh {{ number_format((float) $allocation->fees_amount, 2) }} · penalties KSh {{ number_format((float) $allocation->penalty_amount, 2) }}</small>@endforeach
                </div></article>@empty<p class="empty-inline">No repayments recorded for this loan.</p>@endforelse
                @foreach ($loan->charges as $charge)<article class="client-finance-entry"><div><strong>{{ str_replace('_', ' ', ucfirst($charge->charge_type)) }} · KSh {{ number_format((float) $charge->amount, 2) }} · {{ ucfirst($charge->status) }}</strong><small>{{ $charge->charged_on->format('d M Y') }} · {{ $charge->notes }}</small></div><span>Paid KSh {{ number_format((float) $charge->amount_paid, 2) }}</span></article>@endforeach
            </div>
        @empty
            <div class="empty-table">Financial activity will appear here after the client receives a loan.</div>
        @endforelse
    </section>

    <section class="panel client-support-panel"><div class="panel-heading"><div><h2>Supporting records</h2><p>Secure documents, guarantors and collateral</p></div></div>
        @foreach ($applications as $application)
            <div class="client-support-group"><h3>{{ $application->reference }}</h3>
                @forelse ($application->documents as $document)<a class="client-document-entry" href="{{ route('admin.documents.show', $document) }}"><span><strong>{{ str_replace('_', ' ', ucfirst($document->document_type)) }} · {{ $document->original_name }}</strong><small>{{ $document->mime_type }} · {{ number_format($document->size_bytes / 1024) }} KB · {{ $document->created_at->format('d M Y') }} · Uploaded by {{ $document->uploadedBy?->name ?? 'Client' }}</small></span><span>Download ↓</span></a>@empty<p class="empty-inline">No documents uploaded.</p>@endforelse
                @foreach ($application->guarantors as $guarantor)<article class="client-support-entry"><strong>Guarantor · {{ $guarantor->full_name }}</strong><small>ID {{ $guarantor->national_id }} · {{ $guarantor->phone }} · {{ $guarantor->relationship ?: 'Relationship not specified' }}</small></article>@endforeach
                @foreach ($application->collateralItems as $item)<article class="client-support-entry"><strong>Collateral · {{ $item->description }}</strong><small>{{ $item->quantity }} item(s) · {{ $item->make_model }} · {{ $item->serial_number }} · Estimated KSh {{ number_format((float) $item->estimated_value, 2) }}</small></article>@endforeach
            </div>
        @endforeach
    </section>

    <section class="panel client-activity-panel"><div class="panel-heading"><div><h2>Activity log</h2><p>Recorded actions across the borrower, applications, loans and payments</p></div><span class="count-pill">{{ $activity->count() }}</span></div>
        <div class="client-activity-list">@forelse ($activity as $log)<article><span class="client-activity-dot"></span><div><strong>{{ str_replace(['.', '_'], ' ', ucfirst($log->action)) }}</strong><small>{{ $log->created_at->format('d M Y · H:i') }} · {{ $log->actor?->name ?? 'Online / system' }} · {{ class_basename((string) $log->subject_type) }} #{{ $log->subject_id }}</small>@if ($log->metadata)<p>{{ json_encode($log->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</p>@endif</div></article>@empty<div class="empty-table">No audit events are recorded for this client yet.</div>@endforelse</div>
    </section>
</div>
@endsection
