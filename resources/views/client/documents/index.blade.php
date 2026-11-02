@extends('layouts.client')

@section('title', 'Documents')

@section('content')
    <div class="client-page-heading">
        <div>
            <span class="eyebrow"><span class="eyebrow-dot"></span> SECURE DOCUMENT VAULT</span>
            <h1>Your documents</h1>
            <p>Upload supporting files and download documents attached to your applications.</p>
        </div>
    </div>

    <section class="client-panel-card">
        <div class="client-panel-heading">
            <div><h2>Document vault</h2><p>Your files are stored privately and can only be accessed by you and authorized staff.</p></div>
        </div>

        @if ($documents->isNotEmpty())
            <div class="client-vault-list">
                @foreach ($documents as $document)
                    <article class="client-vault-document">
                        <span class="client-vault-file-icon" aria-hidden="true">▤</span>
                        <div class="client-vault-document-info">
                            <strong>{{ $document->original_name }}</strong>
                            <span>{{ ucwords(str_replace('_', ' ', $document->document_type)) }} · {{ $document->application->reference }}</span>
                            <small>{{ $document->application->product?->name ?? 'Loan application' }} · Uploaded {{ $document->created_at->format('d M Y') }}</small>
                        </div>
                        <a class="button button-quiet button-small" href="{{ route('client.documents.show', $document) }}">Download <span aria-hidden="true">↓</span></a>
                    </article>
                @endforeach
            </div>
            {{ $documents->links() }}
        @else
            <div class="client-empty-state compact">
                <span>▧</span>
                <strong>Your vault is empty</strong>
                <p>Documents you upload for a loan application will appear here.</p>
                <a class="text-link" href="{{ route('client.applications.index') }}">View applications <span aria-hidden="true">→</span></a>
            </div>
        @endif
    </section>

    <section class="client-panel-card">
        <div class="client-panel-heading">
            <div><h2>Upload a document</h2><p>Choose an application currently accepting supporting documents.</p></div>
        </div>

        @forelse ($uploadableApplications as $application)
            <div class="client-vault-upload-card">
                <div class="client-vault-upload-heading">
                    <div><strong>{{ $application->product?->name ?? 'Loan application' }}</strong><span>{{ $application->reference }}</span></div>
                    <span class="client-status">{{ $application->clientStatusLabel() }}</span>
                </div>
                <form class="client-upload-form" method="post" enctype="multipart/form-data" action="{{ route('client.documents.upload', $application) }}">
                    @csrf
                    <label>Document type<select name="document_type" required><option value="">Choose type</option><option value="identity">Identity document</option><option value="income">Income proof</option><option value="residence">Proof of residence</option><option value="bank_statement">Bank statement</option><option value="other">Other supporting document</option></select></label>
                    <label>Choose file<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp" required></label>
                    <button class="button button-quiet button-small" type="submit">Upload securely</button>
                    @error('document')<span class="field-error">{{ $message }}</span>@enderror
                    @error('document_type')<span class="field-error">{{ $message }}</span>@enderror
                </form>
            </div>
        @empty
            <div class="client-empty-state compact">
                <strong>No applications are accepting uploads</strong>
                <p>When your application needs more information, its secure upload form will appear here.</p>
                <a class="text-link" href="{{ route('client.applications.index') }}">Check application status <span aria-hidden="true">→</span></a>
            </div>
        @endforelse
    </section>
@endsection
