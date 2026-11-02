<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationDocument;
use App\Models\ApplicationReview;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeStaff($request);
        $query = $this->visibleApplications($request)->with(['borrower', 'product', 'assignedOfficer']);
        $query->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->string('status')));
        $query->when($request->filled('q'), function ($builder) use ($request): void {
            $term = '%'.$request->string('q')->trim().'%';
            $builder->where(function ($builder) use ($term): void {
                $builder->where('reference', 'like', $term)
                    ->orWhereHas('borrower', fn ($borrower) => $borrower->where('full_name', 'like', $term)->orWhere('national_id', 'like', $term));
            });
        });

        return view('admin.applications.index', [
            'applications' => $query->latest()->paginate(15)->withQueryString(),
            'statuses' => LoanApplication::query()->distinct()->orderBy('status')->pluck('status'),
        ]);
    }

    public function show(Request $request, LoanApplication $application): View
    {
        $this->authorizeView($request, $application);
        $application->load(['borrower', 'product', 'assignedOfficer', 'documents', 'statusHistory.actor', 'reviews.reviewer', 'guarantors', 'collateralItems', 'loanAccount.schedules', 'loanAccount.payments.recorder', 'loanAccount.payments.allocations.schedule', 'loanAccount.disbursements.recorder', 'loanAccount.charges']);

        return view('admin.applications.show', [
            'application' => $application,
            'loanOfficers' => User::query()->where('role', 'loan_officer')->where('is_active', true)->orderBy('name')->get(),
            'availableTransitions' => $this->availableTransitions($request->user(), $application),
        ]);
    }

    public function assign(Request $request, LoanApplication $application, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['administrator', 'management']), 403);
        $data = $request->validate(['assigned_user_id' => ['required', 'exists:users,id']]);
        $officer = User::query()->where('role', 'loan_officer')->where('is_active', true)->findOrFail($data['assigned_user_id']);
        $previousOfficer = $application->assigned_user_id;
        $application->update(['assigned_user_id' => $officer->id]);
        $auditLogger->record($request->user(), 'application.assigned', $application, ['previous_officer_id' => $previousOfficer, 'officer_id' => $officer->id], $request);

        return back()->with('success', 'Application assigned to '.$officer->name.'.');
    }

    public function review(Request $request, LoanApplication $application, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['administrator', 'loan_officer', 'management']), 403);
        abort_if($request->user()->role === 'loan_officer' && $application->assigned_user_id !== $request->user()->id, 403);
        $data = $request->validate([
            'recommendation' => ['nullable', 'in:recommend_approval,recommend_rejection,additional_information'],
            'notes' => ['required', 'string', 'min:3', 'max:3000'],
        ]);

        $review = ApplicationReview::create([
            'loan_application_id' => $application->id,
            'reviewer_id' => $request->user()->id,
            'recommendation' => $data['recommendation'] ?? null,
            'notes' => $data['notes'],
        ]);
        $auditLogger->record($request->user(), 'application.reviewed', $application, ['review_id' => $review->id], $request);

        return back()->with('success', 'Review note added to the application history.');
    }

    public function transition(Request $request, LoanApplication $application, ApplicationWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeView($request, $application);
        $data = $request->validate([
            'status' => ['required', 'string', 'max:64'],
            'remarks' => ['nullable', 'string', 'max:3000'],
            'approved_amount' => ['nullable', 'required_if:status,approved', 'numeric', 'decimal:0,2', 'gt:0'],
            'approved_months' => ['nullable', 'required_if:status,approved', 'integer', 'between:1,120'],
        ]);

        $workflow->transition(
            $application,
            $request->user(),
            $data['status'],
            $data['remarks'] ?? null,
            $data['approved_amount'] ?? null,
            isset($data['approved_months']) ? (int) $data['approved_months'] : null,
        );

        return back()->with('success', 'Application status updated.');
    }

    public function document(Request $request, ApplicationDocument $document): StreamedResponse
    {
        $document->load('application');
        $this->authorizeView($request, $document->application);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->download($document->storage_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeView(Request $request, LoanApplication $application): void
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['administrator', 'loan_officer', 'finance_officer', 'management']), 403);
        abort_if($user->role === 'loan_officer' && $application->assigned_user_id !== $user->id, 403);
        abort_if($user->role === 'finance_officer' && ! in_array($application->status, ['approved', 'finance_processing', 'pending_disbursement', 'disbursed', 'active'], true), 403);
    }

    private function authorizeStaff(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['administrator', 'loan_officer', 'finance_officer', 'management']), 403);
    }

    private function visibleApplications(Request $request): Builder
    {
        $query = LoanApplication::query();

        if ($request->user()->role === 'loan_officer') {
            $query->where('assigned_user_id', $request->user()->id);
        } elseif ($request->user()->role === 'finance_officer') {
            $query->whereIn('status', ['approved', 'finance_processing', 'pending_disbursement', 'disbursed', 'active']);
        }

        return $query;
    }

    /** @return array<int, string> */
    private function availableTransitions(User $user, LoanApplication $application): array
    {
        $roles = [
            'submitted' => ['under_review' => ['administrator', 'loan_officer']],
            'under_review' => ['verification_assessment' => ['administrator', 'loan_officer'], 'additional_information_required' => ['administrator', 'loan_officer'], 'on_hold' => ['administrator', 'loan_officer', 'management']],
            'additional_information_required' => ['under_review' => ['administrator', 'loan_officer']],
            'verification_assessment' => ['pending_approval' => ['administrator', 'loan_officer'], 'additional_information_required' => ['administrator', 'loan_officer'], 'on_hold' => ['administrator', 'loan_officer', 'management']],
            'on_hold' => ['under_review' => ['administrator', 'loan_officer', 'management']],
            'pending_approval' => ['approved' => ['administrator', 'management'], 'rejected' => ['administrator', 'management']],
            'approved' => ['finance_processing' => ['administrator', 'finance_officer']],
            'finance_processing' => ['pending_disbursement' => ['administrator', 'finance_officer']],
            'pending_disbursement' => [],
        ];
        $transitions = [];

        foreach ($roles[$application->status] ?? [] as $status => $allowedRoles) {
            if (in_array($user->role, $allowedRoles, true) && ($user->role !== 'loan_officer' || $application->assigned_user_id === $user->id)) {
                $transitions[] = $status;
            }
        }

        return $transitions;
    }
}
