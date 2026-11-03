<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitLoanApplicationRequest;
use App\Models\LoanProduct;
use App\Models\LoanTermsSetting;
use App\Services\LoanApplicationIntakeService;
use App\Services\LoanRepaymentCalculator;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoanApplicationController extends Controller
{
    public function create(): View
    {
        return view('public.apply', ['products' => LoanProduct::query()->where('is_active', true)->where(fn ($query) => $query->whereNull('repayment_months')->orWhereBetween('repayment_months', [1, 3]))->orderBy('name')->get()]);
    }

    public function store(SubmitLoanApplicationRequest $request, LoanApplicationIntakeService $intake): RedirectResponse|JsonResponse
    {
        $result = $intake->submit($request->validated(), $request);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your application has been received.',
                'reference' => $result['reference'],
                'confirmation_url' => route('apply.received', ['reference' => $result['reference']]),
            ], 201);
        }

        return to_route('apply.received', ['reference' => $result['reference']]);
    }

    public function quote(Request $request, LoanRepaymentCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'loan_product_id' => ['required', 'integer', Rule::exists('loan_products', 'id')->where('is_active', true)],
            'requested_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'requested_months' => ['required', 'integer', 'between:1,3'],
        ]);
        $product = LoanProduct::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('repayment_months')->orWhereBetween('repayment_months', [1, 3]))
            ->find($data['loan_product_id']);

        if (! $product) {
            throw ValidationException::withMessages(['loan_product_id' => 'This loan option is not available for a new application.']);
        }

        $requestedMonths = (int) $data['requested_months'];

        if ($product->repayment_months !== null && $requestedMonths !== $product->repayment_months) {
            throw ValidationException::withMessages(['requested_months' => 'Choose the repayment period configured for this loan option.']);
        }

        $principalCents = Money::toCents((string) $data['requested_amount']);

        if ($product->minimum_amount !== null && $principalCents < Money::toCents((string) $product->minimum_amount)) {
            throw ValidationException::withMessages(['requested_amount' => 'The requested amount is below the minimum for this loan option.']);
        }

        if ($product->maximum_amount !== null && $principalCents > Money::toCents((string) $product->maximum_amount)) {
            throw ValidationException::withMessages(['requested_amount' => 'The requested amount is above the maximum for this loan option.']);
        }

        $settings = LoanTermsSetting::query()->findOrFail(1);
        $summary = $calculator->calculate((string) $data['requested_amount'], $requestedMonths, $settings);

        if (Money::toCents($summary['net_disbursement']) <= 0) {
            throw ValidationException::withMessages(['requested_amount' => 'The payout must remain positive after fees are deducted.']);
        }

        return response()->json($summary);
    }

    public function received(string $reference): View
    {
        return view('public.received', ['reference' => $reference]);
    }
}
