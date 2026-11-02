@extends('layouts.client')

@section('title', $loan->reference)

@section('content')
    <div class="client-page-heading">
        <div>
            <span class="eyebrow"><span class="eyebrow-dot"></span> LOAN DETAILS</span>
            <h1>{{ $loan->application?->product?->name ?? 'Loan account' }}</h1>
            <p>Review this loan’s balance and repayment schedule.</p>
        </div>
        <a class="button button-quiet" href="{{ route('client.loans.index') }}">All loans <span aria-hidden="true">→</span></a>
    </div>

    @include('client.loans._account-card', ['loan' => $loan])
@endsection
