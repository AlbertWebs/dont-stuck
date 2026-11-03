<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanTermsSetting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanTermsSettingController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->role === 'administrator', 403);

        return view('admin.loan-terms-settings.edit', [
            'settings' => LoanTermsSetting::query()->findOrFail(1),
        ]);
    }

    public function update(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->role === 'administrator', 403);

        $data = $request->validate([
            'interest_rate' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:100'],
            'interest_basis' => ['required', 'in:flat_monthly_equal_principal,reducing_monthly_equal_principal'],
            'processing_fee_rate' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:100'],
            'legal_fee_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999'],
        ]);

        $settings = LoanTermsSetting::query()->findOrFail(1);
        $before = $settings->only(array_keys($data));
        $settings->update([...$data, 'updated_by' => $request->user()->id]);
        $auditLogger->record($request->user(), 'loan_terms.updated', $settings, [
            'changed_fields' => array_keys($data),
            'previous_values' => $before,
        ], $request);

        return back()->with('success', 'Loan terms and payment settings saved.');
    }
}
