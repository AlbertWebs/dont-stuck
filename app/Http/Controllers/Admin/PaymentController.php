<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordPaymentRequest;
use App\Models\LoanAccount;
use App\Models\Payment;
use App\Services\PaymentRecordingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(RecordPaymentRequest $request, LoanAccount $loanAccount, PaymentRecordingService $payments): RedirectResponse
    {
        $payment = $payments->record($loanAccount, $request->user(), $request->validated(), $request);

        return to_route('admin.applications.show', $loanAccount->loan_application_id)->with('success', 'Repayment '.$payment->amount.' recorded and allocated to the schedule.');
    }

    public function reverse(Request $request, Payment $payment, PaymentRecordingService $payments): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['administrator', 'finance_officer']), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $payments->reverse($payment, $request->user(), $data['reason'], $request);

        return back()->with('success', 'Repayment reversed. The original transaction remains in the audit history.');
    }
}
