<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitLoanApplicationRequest;
use App\Models\LoanProduct;
use App\Services\LoanApplicationIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    public function received(string $reference): View
    {
        return view('public.received', ['reference' => $reference]);
    }
}
