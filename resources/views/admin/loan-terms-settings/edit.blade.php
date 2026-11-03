@extends('layouts.admin')

@section('title', 'Loan terms')

@section('content')
    <div class="admin-page-heading">
        <div>
            <span class="eyebrow">System settings</span>
            <h1>Loan terms & payment settings</h1>
            <p>These settings apply to new offers and loans. Existing loan accounts retain the terms recorded when they were disbursed.</p>
        </div>
    </div>

    <form method="post" action="{{ route('admin.loan-terms.update') }}" class="loan-terms-settings-form">
        @csrf
        @method('PUT')

        <section class="panel loan-terms-settings-panel">
            <div class="panel-heading">
                <div><h2>Interest and fees</h2><p>Fees are deducted from the approved principal before the payout is sent.</p></div>
            </div>

            <div class="field-grid loan-terms-settings-fields">
                <div class="field">
                    <label for="interest-rate">Monthly interest rate (%)</label>
                    <input id="interest-rate" name="interest_rate" type="number" min="0" max="100" step="0.0001" value="{{ old('interest_rate', $settings->interest_rate) }}" required>
                    @error('interest_rate')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="interest-basis">Interest calculation method</label>
                    <select id="interest-basis" name="interest_basis" required>
                        <option value="flat_monthly_equal_principal" @selected(old('interest_basis', $settings->interest_basis) === 'flat_monthly_equal_principal')>Flat monthly interest on original principal</option>
                        <option value="reducing_monthly_equal_principal" @selected(old('interest_basis', $settings->interest_basis) === 'reducing_monthly_equal_principal')>Reducing balance each month</option>
                    </select>
                    @error('interest_basis')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="processing-fee-rate">Processing fee (%)</label>
                    <input id="processing-fee-rate" name="processing_fee_rate" type="number" min="0" max="100" step="0.0001" value="{{ old('processing_fee_rate', $settings->processing_fee_rate) }}" required>
                    @error('processing_fee_rate')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="legal-fee-amount">Legal fee (KSh)</label>
                    <input id="legal-fee-amount" name="legal_fee_amount" type="number" min="0" step="0.01" value="{{ old('legal_fee_amount', $settings->legal_fee_amount) }}" required>
                    @error('legal_fee_amount')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>

            <div class="loan-terms-settings-footer">
                <p>Both the processing fee and legal fee are withheld from the payout. Monthly installments cover principal and interest.</p>
                <button class="button button-dark" type="submit">Save loan settings <span aria-hidden="true">↗</span></button>
            </div>
        </section>
    </form>
@endsection
