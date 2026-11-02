<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDocument;
use App\Models\LoanApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $borrower = $request->user()->borrower()->firstOrFail();
        $documents = ApplicationDocument::query()
            ->whereHas('application', fn (Builder $query): Builder => $query->where('borrower_id', $borrower->id))
            ->with('application.product')
            ->latest()
            ->paginate(15)
            ->withQueryString();
        $uploadableApplications = $borrower->applications()
            ->with('product')
            ->whereIn('status', LoanApplication::CLIENT_DOCUMENT_UPLOAD_STATUSES)
            ->latest()
            ->get();

        return view('client.documents.index', [
            'documents' => $documents,
            'uploadableApplications' => $uploadableApplications,
        ]);
    }

    public function download(Request $request, ApplicationDocument $document): StreamedResponse
    {
        $document->load('application');
        abort_unless($document->application->borrower_id === $request->user()->borrower?->id, 404);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->download($document->storage_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
