<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\LoanAccount;
use App\Models\RepaymentSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        $paymentVisibility = function (Builder|HasMany $payments) use ($request): void {
            if ($request->user()->role === 'loan_officer') {
                $payments->whereHas('loanAccount.application', fn (Builder $applications) => $applications->where('assigned_user_id', $request->user()->id));
            }
        };

        $borrowers = Borrower::query()
            ->whereHas('loanAccounts.payments', $paymentVisibility)
            ->with(['loanAccounts' => function (HasMany $accounts) use ($paymentVisibility): void {
                $accounts->whereHas('payments', $paymentVisibility)
                    ->with(['payments' => function (HasMany $payments) use ($paymentVisibility): void {
                        $paymentVisibility($payments);
                        $payments->latest('paid_on')->latest('id');
                    }]);
            }])
            ->orderBy('full_name')
            ->paginate(15)
            ->through(function (Borrower $borrower): array {
                $payments = $borrower->loanAccounts->flatMap(fn (LoanAccount $account) => $account->payments);

                return [
                    'borrower' => $borrower,
                    'loan_accounts' => $borrower->loanAccounts,
                    'receipt_count' => $payments->count(),
                    'posted_total' => (float) $payments->where('status', 'posted')->sum('amount'),
                ];
            });

        return view('admin.repayments', ['borrowers' => $borrowers]);
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
