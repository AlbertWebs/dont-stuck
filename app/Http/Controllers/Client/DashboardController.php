<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\LoanAccount;
use App\Models\RepaymentSchedule;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $borrower = $request->user()->borrower()->firstOrFail();
        $applications = $borrower->applications()->with('product')->latest()->limit(5)->get();
        $loans = $borrower->loanAccounts()->with(['application.product', 'schedules'])->latest()->get();
        $activeLoans = $loans->whereIn('status', ['active', 'partially_repaid', 'overdue', 'defaulted'])->values();
        $outstandingCents = $activeLoans->sum(fn (LoanAccount $loan): int => Money::toCents($loan->outstandingBalance()));
        $upcomingInstallment = $activeLoans
            ->flatMap(fn (LoanAccount $loan) => $loan->schedules)
            ->filter(fn (RepaymentSchedule $schedule): bool => in_array($schedule->status, ['pending', 'partial', 'overdue'], true))
            ->sortBy('due_date')
            ->first();

        return view('client.dashboard', [
            'borrower' => $borrower,
            'applications' => $applications,
            'applicationCount' => $borrower->applications()->count(),
            'activeLoans' => $activeLoans,
            'loanCount' => $activeLoans->count(),
            'outstandingBalance' => Money::fromCents($outstandingCents),
            'upcomingInstallment' => $upcomingInstallment,
        ]);
    }
}
