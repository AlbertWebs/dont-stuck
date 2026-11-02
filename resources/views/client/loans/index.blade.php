@extends('layouts.client')

@section('title', 'Loans')

@section('content')
    <div class="client-page-heading"><div><span class="eyebrow"><span class="eyebrow-dot"></span> LOAN OVERVIEW</span><h1>Your loans</h1><p>Review balances, repayment schedules and posted payments.</p></div><a class="button button-dark" href="{{ route('apply') }}">Apply for another loan <span aria-hidden="true">↗</span></a></div>

    @forelse ($loans as $loan)
        @include('client.loans._account-card', ['loan' => $loan])
    @empty
        <section class="client-panel-card client-empty-state"><span>◉</span><strong>No loans yet</strong><p>Approved and disbursed loan accounts will appear here.</p></section>
    @endforelse
    {{ $loans->links() }}

    <section class="client-panel-card client-payment-history">
        <div class="client-panel-heading"><div><h2>Payment history</h2><p>Posted receipts linked to your loan accounts.</p></div></div>
        @if ($payments->isNotEmpty())
            <div class="client-table-wrap"><table class="client-table"><thead><tr><th>Date</th><th>Loan</th><th>Method</th><th>Principal</th><th>Interest & fees</th><th>Penalty</th><th>Receipt amount</th><th>Status</th></tr></thead><tbody>
                @foreach ($payments as $payment)
                    @php($principalPaid = $payment->allocations->sum('principal_amount'))
                    @php($interestAndFeesPaid = $payment->allocations->sum('interest_amount') + $payment->allocations->sum('fees_amount'))
                    @php($penaltyPaid = $payment->allocations->sum('penalty_amount'))
                    <tr><td>{{ $payment->paid_on->format('d M Y') }}</td><td>{{ $payment->loanAccount->reference }}</td><td>{{ ucwords(str_replace('_', ' ', $payment->method)) }}</td><td>KSh {{ number_format((float) $principalPaid, 2) }}</td><td>KSh {{ number_format((float) $interestAndFeesPaid, 2) }}</td><td>KSh {{ number_format((float) $penaltyPaid, 2) }}</td><td><strong>KSh {{ number_format((float) $payment->amount, 2) }}</strong></td><td><span class="client-status">{{ ucfirst($payment->status) }}</span></td></tr>
                @endforeach
            </tbody></table></div>
        @else
            <div class="client-empty-state compact"><strong>No payments recorded</strong><p>Payments recorded against your loans will appear here.</p></div>
        @endif
        {{ $payments->links() }}
    </section>
@endsection
