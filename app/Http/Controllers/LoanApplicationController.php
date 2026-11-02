<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitLoanApplicationRequest;
use App\Models\LoanProduct;
use App\Services\LoanApplicationIntakeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoanApplicationController extends Controller
{
    public function create(): View
    {
        return view('public.apply', ['products' => LoanProduct::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(SubmitLoanApplicationRequest $request, LoanApplicationIntakeService $intake): RedirectResponse
    {
        $result = $intake->submit($request->validated(), $request);

        return to_route('apply.received', ['reference' => $result['reference']]);
    }

    public function received(string $reference): View
    {
        return view('public.received', ['reference' => $reference]);
    }
}
