@extends('layouts.admin')

@section('title', 'Repayments')
@section('content')
<div class="admin-page-heading"><div><span class="eyebrow">Collections ledger</span><h1>Repayments</h1><p>Manual receipts and controlled reversals recorded in the LMS.</p></div></div>
<section class="panel recent-panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>DATE</th><th>RECEIPT</th><th>LOAN</th><th>BORROWER</th><th>METHOD</th><th>REFERENCE</th><th>AMOUNT</th><th>STATUS</th></tr></thead><tbody>@forelse ($payments as $payment)<tr><td>{{ $payment->paid_on->format('d M Y') }}</td><td>#{{ $payment->id }}</td><td>{{ $payment->loanAccount->reference }}</td><td>{{ $payment->loanAccount->borrower->full_name }}</td><td>{{ str_replace('_', ' ', ucfirst($payment->method)) }}</td><td>{{ $payment->external_reference ?: '—' }}</td><td>KSh {{ number_format((float) $payment->amount, 2) }}</td><td><span class="status-badge status-{{ $payment->status }}">{{ ucfirst($payment->status) }}</span></td></tr>@empty<tr><td colspan="8"><div class="empty-table">No repayment receipts have been recorded.</div></td></tr>@endforelse</tbody></table></div><div class="pagination-wrap">{{ $payments->links() }}</div></section>
@endsection
