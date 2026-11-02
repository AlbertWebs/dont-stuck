<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmDisbursementRequest;
use App\Models\LoanApplication;
use App\Services\DisbursementService;
use Illuminate\Http\RedirectResponse;

class DisbursementController extends Controller
{
    public function store(ConfirmDisbursementRequest $request, LoanApplication $application, DisbursementService $disbursementService): RedirectResponse
    {
        $account = $disbursementService->confirm($application, $request->user(), $request->validated(), $request);

        return to_route('admin.applications.show', $application)->with('success', 'Manual disbursement recorded. Loan account '.$account->reference.' is active.');
    }
}
