<section class="client-panel-card client-loan-card" id="loan-{{ $loan->id }}">
    <div class="client-application-heading"><div><span class="client-reference">{{ $loan->reference }}</span><h2>{{ $loan->application?->product?->name ?? 'Loan account' }}</h2><small>Disbursed {{ $loan->disbursed_at?->format('d M Y') ?? 'Date not available' }}</small></div><span class="client-status">{{ ucwords(str_replace('_', ' ', $loan->status)) }}</span></div>
    <div class="client-application-facts"><div><small>Original principal</small><strong>KSh {{ number_format((float) $loan->principal_amount, 2) }}</strong></div><div><small>Outstanding balance</small><strong>KSh {{ number_format((float) $loan->outstandingBalance(), 2) }}</strong></div><div><small>Interest rate</small><strong>{{ number_format((float) $loan->interest_rate, 2) }}% ({{ $loan->interest_basis }})</strong></div><div><small>Maturity date</small><strong>{{ $loan->maturity_date?->format('d M Y') ?? 'Not set' }}</strong></div></div>
    <div class="client-schedule"><h3>Repayment schedule</h3>
        @if ($loan->schedules->isNotEmpty())
            <div class="client-table-wrap"><table class="client-table"><thead><tr><th>Installment</th><th>Due date</th><th>Amount due</th><th>Paid</th><th>Status</th></tr></thead><tbody>
                @foreach ($loan->schedules as $schedule)
                    @php($due = (float) $schedule->principal_due + (float) $schedule->interest_due + (float) $schedule->fees_due + (float) $schedule->penalties_due)
                    @php($paid = (float) $schedule->principal_paid + (float) $schedule->interest_paid + (float) $schedule->fees_paid + (float) $schedule->penalties_paid)
                    <tr><td>{{ $schedule->installment_number }}</td><td>{{ $schedule->due_date->format('d M Y') }}</td><td>KSh {{ number_format($due, 2) }}</td><td>KSh {{ number_format($paid, 2) }}</td><td><span class="client-status">{{ ucfirst($schedule->status) }}</span></td></tr>
                @endforeach
            </tbody></table></div>
        @else
            <p class="client-muted">No repayment schedule is available for this loan yet.</p>
        @endif
    </div>
</section>
