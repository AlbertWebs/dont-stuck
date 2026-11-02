@extends('layouts.site')

@section('title', 'Application received')
@section('description', 'Your loan application has been received by Don’t Stuck Solutions.')

@section('content')
<section class="section confirmation-section"><div class="confirmation-card"><div class="confirmation-check">✓</div><span class="eyebrow">Application received</span><h1>Thank you for<br><em>getting in touch.</em></h1><p>Our team has received your application and will review the information you provided.</p><div class="reference-box"><small>YOUR APPLICATION REFERENCE</small><strong>{{ $reference }}</strong></div><p class="fine-print">Keep this reference for your records. For questions, call <a href="tel:+254725228380">+254 725 228 380</a>. Please don’t share it publicly.</p><a class="button button-dark" href="{{ route('home') }}">Return to homepage <span aria-hidden="true">→</span></a></div></section>
@endsection
