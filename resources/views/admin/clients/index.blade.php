@extends('layouts.admin')

@section('title', 'Client records')
@section('content')
<div class="admin-page-heading"><div><span class="eyebrow">Client information</span><h1>Client records</h1><p>Open a complete borrower file or export a bank-ready financial history workbook.</p></div></div>
<section class="panel list-panel client-record-list">
    <form class="filter-bar" method="get">
        <label class="search-field"><span>⌕</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, ID, phone or email" aria-label="Search client records"></label>
        <button class="button button-dark button-small" type="submit">Search clients</button>
        @if (request()->filled('q'))<a class="clear-filter" href="{{ route('admin.clients.index') }}">Clear</a>@endif
    </form>
    <div class="list-summary"><span>{{ number_format($borrowers->total()) }} client records</span><span>Each record contains applications, decisions, loan activity and export tools</span></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>CLIENT</th><th>NATIONAL ID</th><th>PHONE</th><th>APPLICATIONS</th><th>LOAN ACCOUNTS</th><th>ADDED</th><th></th></tr></thead><tbody>
        @forelse ($borrowers as $borrower)
            <tr><td><strong>{{ $borrower->full_name }}</strong><small>{{ $borrower->email ?: 'Email not provided' }}</small></td><td>{{ $borrower->national_id }}</td><td>{{ $borrower->phone }}</td><td><span class="count-pill">{{ $borrower->applications_count }}</span></td><td><span class="count-pill">{{ $borrower->loan_accounts_count }}</span></td><td>{{ $borrower->created_at->format('d M Y') }}</td><td><a class="button button-outline-dark button-small" href="{{ route('admin.clients.show', $borrower) }}">Open client record <span aria-hidden="true">→</span></a></td></tr>
        @empty
            <tr><td colspan="7"><div class="empty-table">No client records match your search.</div></td></tr>
        @endforelse
    </tbody></table></div>
    <div class="pagination-wrap">{{ $borrowers->links() }}</div>
</section>
@endsection
