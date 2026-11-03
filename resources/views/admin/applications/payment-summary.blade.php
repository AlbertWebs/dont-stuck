<section class="panel payment-summary admin-payment-summary">
    <div class="panel-heading">
        <div><span class="eyebrow">Current estimate</span><h2>Proposed payment summary</h2><p>Calculated from the requested or approved terms and current system settings.</p></div>
        <span class="payment-summary-method">{{ number_format((float) $paymentSummary['interest_rate'], 2) }}% monthly · {{ $paymentSummary['interest_basis'] === 'flat_monthly_equal_principal' ? 'Flat monthly' : 'Reducing balance' }}</span>
    </div>
    <dl class="payment-summary-totals">
        <div><dt>Principal</dt><dd>KSh {{ number_format((float) $paymentSummary['principal'], 2) }}</dd></div>
        <div><dt>Processing fee withheld</dt><dd>KSh {{ number_format((float) $paymentSummary['processing_fee'], 2) }}</dd></div>
        <div><dt>Legal fee withheld</dt><dd>KSh {{ number_format((float) $paymentSummary['legal_fee'], 2) }}</dd></div>
        <div><dt>Estimated payout</dt><dd>KSh {{ number_format((float) $paymentSummary['net_disbursement'], 2) }}</dd></div>
        <div><dt>Total interest</dt><dd>KSh {{ number_format((float) $paymentSummary['total_interest'], 2) }}</dd></div>
        <div><dt>Total repayment</dt><dd>KSh {{ number_format((float) $paymentSummary['total_repayment'], 2) }}</dd></div>
        <div><dt>Interest and fees</dt><dd>KSh {{ number_format((float) $paymentSummary['total_cost'], 2) }}</dd></div>
    </dl>
    <h3>Installments · {{ $paymentSummary['term_months'] }} month(s)</h3>
    <ol class="payment-installment-list">
        @foreach ($paymentSummary['installments'] as $installment)
            <li><span>Installment {{ $installment['number'] }} · Principal KSh {{ number_format((float) $installment['principal'], 2) }} + interest KSh {{ number_format((float) $installment['interest'], 2) }}</span><strong>KSh {{ number_format((float) $installment['total'], 2) }}</strong></li>
        @endforeach
    </ol>
    <p class="payment-summary-note">Fees are withheld from payout and excluded from the installments above. Late charges, if applicable, are not included.</p>
</section>
