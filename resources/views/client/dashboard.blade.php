@extends('layouts.client')

@section('title', 'Dashboard')

@section('content')
    <div class="client-page-heading">
        <div><span class="eyebrow"><span class="eyebrow-dot"></span> ACCOUNT OVERVIEW</span><h1>Welcome back, {{ $borrower->full_name }}</h1><p>Here’s the latest on your applications and loans.</p></div>
        <a class="button button-dark" href="{{ route('apply') }}">Apply for a loan <span aria-hidden="true">↗</span></a>
    </div>

    <div class="client-metric-grid">
        <article class="client-metric-card"><span>Applications</span><strong>{{ $applicationCount }}</strong><small>Submitted to our team</small></article>
        <article class="client-metric-card"><span>Current loans</span><strong>{{ $loanCount }}</strong><small>Open loan accounts</small></article>
        <article class="client-metric-card"><span>Outstanding balance</span><strong>KSh {{ number_format((float) $outstandingBalance, 2) }}</strong><small>Across your open loans</small></article>
        <article class="client-metric-card client-next-payment"><span>Next payment</span>
            @if ($upcomingInstallment)
                <strong>KSh {{ number_format((float) $upcomingInstallment->outstandingAmount(), 2) }}</strong>
                <small>Due {{ $upcomingInstallment->due_date->format('d M Y') }}</small>
            @else
                <strong>—</strong><small>No payment due</small>
            @endif
        </article>
    </div>

    <section class="client-panel-card">
        <div class="client-panel-heading"><div><h2>Recent applications</h2><p>Follow each application’s latest status.</p></div><a href="{{ route('client.applications.index') }}">View all <span aria-hidden="true">→</span></a></div>
        @if ($applications->isNotEmpty())
            <div class="client-table-wrap"><table class="client-table"><thead><tr><th>Reference</th><th>Product</th><th>Requested</th><th>Status</th><th>Submitted</th></tr></thead><tbody>
                @foreach ($applications as $application)
                    <tr><td><strong>{{ $application->reference }}</strong></td><td>{{ $application->product?->name ?? 'Loan application' }}</td><td>KSh {{ number_format((float) $application->requested_amount, 2) }}</td><td><span class="client-status">{{ $application->clientStatusLabel() }}</span></td><td>{{ $application->created_at->format('d M Y') }}</td></tr>
                @endforeach
            </tbody></table></div>
        @else
            <div class="client-empty-state"><span>▤</span><strong>No applications yet</strong><p>Your submitted applications will appear here.</p><a class="text-link" href="{{ route('apply') }}">Start an application <span aria-hidden="true">↗</span></a></div>
        @endif
    </section>

    <section class="client-panel-card">
        <div class="client-panel-heading"><div><h2>Open loans</h2><p>Balances and maturity details for your current loans.</p></div><a href="{{ route('client.loans.index') }}">View loans <span aria-hidden="true">→</span></a></div>
        @if ($activeLoans->isNotEmpty())
            <div class="client-loan-preview-grid">
                @foreach ($activeLoans->take(3) as $loan)
                    <article class="client-loan-preview"><div><span>{{ $loan->reference }}</span><span class="client-status">{{ ucwords(str_replace('_', ' ', $loan->status)) }}</span></div><h3>{{ $loan->application?->product?->name ?? 'Loan account' }}</h3><p>Outstanding balance</p><strong>KSh {{ number_format((float) $loan->outstandingBalance(), 2) }}</strong><small>Matures {{ $loan->maturity_date?->format('d M Y') ?? 'Not set' }}</small></article>
                @endforeach
            </div>
        @else
            <div class="client-empty-state compact"><strong>No open loans</strong><p>Any approved and disbursed loan will appear here.</p></div>
        @endif
    </section>
@endsection
