<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\RepaymentSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeStaff($request);
        $applications = $this->visibleApplications($request);
        $recentApplications = (clone $applications)->with(['borrower', 'product', 'assignedOfficer'])->latest()->limit(7)->get();
        $statusCounts = (clone $applications)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        return view('admin.dashboard', [
            'totalApplications' => (clone $applications)->count(),
            'pendingApplications' => (clone $applications)->whereIn('status', ['submitted', 'under_review', 'verification_assessment', 'pending_approval', 'additional_information_required'])->count(),
            'approvedApplications' => (clone $applications)->whereIn('status', ['approved', 'finance_processing', 'pending_disbursement', 'disbursed', 'active'])->count(),
            'disbursedTotal' => $this->visibleLoanAccounts($request)->sum('disbursed_amount'),
            'activeLoans' => $this->visibleLoanAccounts($request)->whereIn('status', ['active', 'partially_repaid', 'overdue'])->count(),
            'repaymentsCollected' => $this->visiblePayments($request)->where('status', 'posted')->sum('amount'),
            'overdueCount' => $this->visibleSchedules($request)->whereDate('due_date', '<', today())->whereIn('status', ['pending', 'partial', 'overdue'])->count(),
            'statusCounts' => $statusCounts,
            'recentApplications' => $recentApplications,
        ]);
    }

    public function reports(Request $request): View
    {
        $this->authorizeStaff($request);
        $applications = $this->visibleApplications($request);

        return view('admin.reports', [
            'applicationCount' => (clone $applications)->count(),
            'applicationStatuses' => (clone $applications)->select('status', DB::raw('count(*) as total'))->groupBy('status')->orderBy('status')->get(),
            'disbursedTotal' => $this->visibleLoanAccounts($request)->sum('disbursed_amount'),
            'repaymentsTotal' => $this->visiblePayments($request)->where('status', 'posted')->sum('amount'),
            'overdueSchedules' => $this->visibleSchedules($request)->with('loanAccount.borrower')->whereDate('due_date', '<', today())->whereIn('status', ['pending', 'partial', 'overdue'])->latest('due_date')->paginate(20),
        ]);
    }

    public function exportApplicationsCsv(Request $request): StreamedResponse
    {
        $this->authorizeStaff($request);
        $applications = $this->visibleApplications($request)
            ->with(['borrower', 'product', 'assignedOfficer'])
            ->latest()
            ->get();

        return response()->streamDownload(function () use ($applications): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Application reference', 'Applicant', 'Product', 'Requested amount KES', 'Status', 'Assigned officer', 'Submitted at']);

            foreach ($applications as $application) {
                fputcsv($stream, array_map($this->safeCsvCell(...), [
                    $application->reference,
                    $application->borrower->full_name,
                    $application->product->name,
                    $application->requested_amount,
                    $application->status,
                    $application->assignedOfficer?->name ?? '',
                    $application->created_at->toIso8601String(),
                ]));
            }

            fclose($stream);
        }, 'loan-applications-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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

    private function visibleLoanAccounts(Request $request): Builder
    {
        $query = LoanAccount::query();

        if ($request->user()->role === 'loan_officer') {
            $query->whereHas('application', fn (Builder $applications) => $applications->where('assigned_user_id', $request->user()->id));
        }

        return $query;
    }

    private function visiblePayments(Request $request): Builder
    {
        $query = Payment::query();

        if ($request->user()->role === 'loan_officer') {
            $query->whereHas('loanAccount.application', fn (Builder $applications) => $applications->where('assigned_user_id', $request->user()->id));
        }

        return $query;
    }

    private function visibleSchedules(Request $request): Builder
    {
        $query = RepaymentSchedule::query();

        if ($request->user()->role === 'loan_officer') {
            $query->whereHas('loanAccount.application', fn (Builder $applications) => $applications->where('assigned_user_id', $request->user()->id));
        }

        return $query;
    }

    private function safeCsvCell(string|int|float|null $value): string|int|float
    {
        $cell = (string) ($value ?? '');

        return preg_match('/^[=+@\-\t\r]/', $cell) ? "'".$cell : $cell;
    }
}
