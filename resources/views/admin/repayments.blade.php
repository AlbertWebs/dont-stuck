@extends('layouts.admin')

@section('title', 'Repayments')
@section('content')
<div class="admin-page-heading"><div><span class="eyebrow">Collections ledger</span><h1>Repayments</h1><p>Manual receipts and controlled reversals recorded in the LMS.</p></div></div>
<div class="repayment-borrower-list">
    @forelse ($borrowers as $group)
        <section class="panel borrower-ledger-card">
            <header class="borrower-ledger-heading">
                <div class="borrower-ledger-identity">
                    <span class="borrower-ledger-avatar" aria-hidden="true">{{ strtoupper(substr($group['borrower']->full_name, 0, 1)) }}</span>
                    <div><h2>{{ $group['borrower']->full_name }}</h2><p>{{ $group['borrower']->phone }}@if ($group['borrower']->email) · {{ $group['borrower']->email }}@endif</p></div>
                </div>
                <div class="borrower-ledger-summary"><div><small>RECEIPTS</small><strong>{{ number_format($group['receipt_count']) }}</strong></div><div><small>POSTED TOTAL</small><strong>KSh {{ number_format($group['posted_total'], 2) }}</strong></div></div>
            </header>

            @foreach ($group['loan_accounts'] as $loanAccount)
                <div class="borrower-loan-group">
                    <div class="borrower-loan-heading"><div><span>LOAN ACCOUNT</span><strong>{{ $loanAccount->reference }}</strong></div><span class="status-badge status-{{ $loanAccount->status }}">{{ str_replace('_', ' ', ucfirst($loanAccount->status)) }}</span></div>
                    <div class="table-wrap borrower-payment-table-wrap"><table class="data-table"><thead><tr><th>DATE</th><th>RECEIPT</th><th>METHOD</th><th>REFERENCE</th><th>AMOUNT</th><th>STATUS</th></tr></thead><tbody>
                        @foreach ($loanAccount->payments as $payment)
                            <tr><td>{{ $payment->paid_on->format('d M Y') }}</td><td>#{{ $payment->id }}</td><td>{{ str_replace('_', ' ', ucfirst($payment->method)) }}</td><td>{{ $payment->external_reference ?: '—' }}</td><td>KSh {{ number_format((float) $payment->amount, 2) }}</td><td><span class="status-badge status-{{ $payment->status }}">{{ ucfirst($payment->status) }}</span></td></tr>
                        @endforeach
                    </tbody></table></div>
                </div>
            @endforeach
        </section>
    @empty
        <section class="panel recent-panel"><div class="empty-table">No repayment receipts have been recorded.</div></section>
    @endforelse
</div>
<div class="pagination-wrap">{{ $borrowers->links() }}</div>
@endsection
