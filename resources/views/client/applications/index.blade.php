@extends('layouts.client')

@section('title', 'Applications')

@section('content')
    <div class="client-page-heading"><div><span class="eyebrow"><span class="eyebrow-dot"></span> APPLICATION TRACKER</span><h1>Your applications</h1><p>Check progress and securely manage supporting documents.</p></div><a class="button button-dark" href="{{ route('apply') }}">Apply for a loan <span aria-hidden="true">↗</span></a></div>

    @forelse ($applications as $application)
        <section class="client-panel-card client-application-card">
            <div class="client-application-heading"><div><span class="client-reference">{{ $application->reference }}</span><h2>{{ $application->product?->name ?? 'Loan application' }}</h2><small>Submitted {{ $application->created_at->format('d M Y') }}</small></div><span class="client-status">{{ $application->clientStatusLabel() }}</span></div>
            <div class="client-application-facts"><div><small>Requested amount</small><strong>KSh {{ number_format((float) $application->requested_amount, 2) }}</strong></div><div><small>Requested term</small><strong>{{ $application->requested_months ? $application->requested_months.' months' : 'To be discussed' }}</strong></div><div><small>Purpose</small><strong>{{ $application->purpose }}</strong></div></div>
            <div class="client-documents"><div><h3>Documents</h3><p>Uploaded files are stored privately and only available to your account and authorized staff.</p></div>
                @if ($application->documents->isNotEmpty())
                    <ul>@foreach ($application->documents as $document)<li><span>{{ $document->original_name }}</span><a href="{{ route('client.documents.show', $document) }}">Download <span aria-hidden="true">↓</span></a></li>@endforeach</ul>
                @else
                    <p class="client-muted">No documents uploaded for this application.</p>
                @endif
                @if ($application->allowsClientDocumentUpload())
                    <form class="client-upload-form" method="post" enctype="multipart/form-data" action="{{ route('client.applications.documents.store', $application) }}">
                        @csrf
                        <label>Document type<select name="document_type" required><option value="">Choose type</option><option value="identity">Identity document</option><option value="income">Income proof</option><option value="residence">Proof of residence</option><option value="bank_statement">Bank statement</option><option value="other">Other supporting document</option></select></label>
                        <label>Choose file<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp" required></label>
                        <button class="button button-quiet button-small" type="submit">Upload securely</button>
                        @error('document')<span class="field-error">{{ $message }}</span>@enderror
                        @error('document_type')<span class="field-error">{{ $message }}</span>@enderror
                    </form>
                @endif
            </div>
        </section>
    @empty
        <section class="client-panel-card client-empty-state"><span>▤</span><strong>No applications found</strong><p>Once you submit an application, its progress will be shown here.</p><a class="button button-dark" href="{{ route('apply') }}">Start an application <span aria-hidden="true">↗</span></a></section>
    @endforelse

    {{ $applications->links() }}
@endsection
