<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanProductController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->role === 'administrator', 403);

        return view('admin.products.index', ['products' => LoanProduct::query()->withCount('applications')->orderBy('name')->get()]);
    }

    public function update(Request $request, LoanProduct $product, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $data = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'minimum_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'maximum_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999', 'gte:minimum_amount'],
            'repayment_months' => ['nullable', 'integer', 'between:1,3'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'interest_basis' => ['nullable', 'in:flat_monthly_equal_principal,reducing_monthly_equal_principal'],
            'processing_fee_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commissioning_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'daily_late_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'late_penalty_start' => ['nullable', 'in:day_after_due,on_due_date'],
            'allocation_order' => ['nullable', 'in:penalties_fees_interest_principal,fees_penalties_interest_principal,principal_interest_fees_penalties'],
            'fee_collection_method' => ['nullable', 'in:first_installment,deducted_from_disbursement'],
            'default_threshold_days' => ['nullable', 'integer', 'between:1,3650'],
            'is_active' => ['required', 'boolean'],
        ]);

        $before = $product->only(array_keys($data));
        $product->update($data);
        $auditLogger->record($request->user(), 'loan_product.updated', $product, ['changed_fields' => array_keys($data)], $request);

        return back()->with('success', 'Product settings saved.')->with('previous_product_settings', $before);
    }
}
