<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanAccount;
use App\Models\Payment;
use App\Models\RepaymentSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function loans(Request $request): View
    {
        $query = $this->visibleAccounts($request)->with(['borrower', 'application.product', 'schedules']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('admin.loans', ['accounts' => $query->latest()->paginate(20)->withQueryString()]);
    }

    public function repayments(Request $request): View
    {
        $query = Payment::query()->with(['loanAccount.borrower', 'loanAccount.application']);

        if ($request->user()->role === 'loan_officer') {
            $query->whereHas('loanAccount.application', fn (Builder $applications) => $applications->where('assigned_user_id', $request->user()->id));
        }

        return view('admin.repayments', ['payments' => $query->latest('paid_on')->paginate(20)->withQueryString()]);
    }

    public function overdue(Request $request): View
    {
        $query = RepaymentSchedule::query()
            ->with(['loanAccount.borrower', 'loanAccount.application'])
            ->whereDate('due_date', '<', today())
            ->whereIn('status', ['pending', 'partial', 'overdue']);

        if ($request->user()->role === 'loan_officer') {
            $query->whereHas('loanAccount.application', fn (Builder $applications) => $applications->where('assigned_user_id', $request->user()->id));
        }

        return view('admin.overdue', ['schedules' => $query->orderBy('due_date')->paginate(20)->withQueryString()]);
    }

    private function visibleAccounts(Request $request): Builder
    {
        $query = LoanAccount::query();

        if ($request->user()->role === 'loan_officer') {
            $query->whereHas('application', fn (Builder $applications) => $applications->where('assigned_user_id', $request->user()->id));
        } elseif ($request->user()->role === 'finance_officer') {
            $query->whereIn('status', ['pending_disbursement', 'active', 'partially_repaid', 'overdue', 'defaulted', 'fully_repaid']);
        }

        return $query;
    }
}
