<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\LoanAccount;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function show(Request $request, LoanAccount $loanAccount): View
    {
        $borrower = $request->user()->borrower()->firstOrFail();
        $loan = $borrower->loanAccounts()
            ->whereKey($loanAccount->id)
            ->with(['application.product', 'schedules'])
            ->firstOrFail();

        return view('client.loans.show', ['loan' => $loan]);
    }

    public function index(Request $request): View
    {
        $borrower = $request->user()->borrower()->firstOrFail();
        $loans = $borrower->loanAccounts()
            ->with(['application.product', 'schedules'])
            ->latest()
            ->paginate(10, ['*'], 'loans_page')
            ->withQueryString();
        $payments = Payment::query()
            ->whereHas('loanAccount', function (Builder $query) use ($borrower): void {
                $query->where('borrower_id', $borrower->id);
            })
            ->with(['loanAccount.application.product', 'allocations'])
            ->latest('paid_on')
            ->paginate(10, ['*'], 'payments_page')
            ->withQueryString();

        return view('client.loans.index', ['loans' => $loans, 'payments' => $payments]);
    }
}
