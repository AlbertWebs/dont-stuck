<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadClientDocumentRequest;
use App\Models\ApplicationDocument;
use App\Models\LoanApplication;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $borrower = $request->user()->borrower()->firstOrFail();
        $applications = $borrower->applications()
            ->with(['product', 'documents'])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('client.applications.index', ['applications' => $applications]);
    }

    public function uploadDocument(
        UploadClientDocumentRequest $request,
        LoanApplication $application,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        abort_unless($application->allowsClientDocumentUpload(), 403);

        $file = $request->file('document');
        $path = $file->store('loan-applications/'.$application->reference, 'local');
        $document = ApplicationDocument::create([
            'loan_application_id' => $application->id,
            'document_type' => $request->validated('document_type'),
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
            'uploaded_by' => $request->user()->id,
        ]);

        $auditLogger->record($request->user(), 'client.document_uploaded', $document, ['application_id' => $application->id], $request);

        $destination = $request->routeIs('client.documents.upload')
            ? 'client.documents.index'
            : 'client.applications.index';

        return to_route($destination)->with('success', 'Your document was uploaded securely.');
    }
}
