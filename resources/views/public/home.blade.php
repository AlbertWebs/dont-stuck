@extends('layouts.site')

@section('title', $pageContent['meta_title'])
@section('description', $pageContent['meta_description'])

@section('content')
<section class="hero-section">
    <div class="shell hero-grid">
        <div class="hero-copy">
            <span class="eyebrow"><span class="eyebrow-dot"></span> Lending with a human touch</span>
            <h1>{{ $pageContent['content']['hero_heading'] }}</h1>
            <p class="hero-lead">{{ $pageContent['content']['hero_copy'] }}</p>
            <div class="hero-actions"><a class="button button-dark" href="{{ route('apply') }}">Start an application <span aria-hidden="true">↗</span></a><a class="text-link" href="{{ route('products') }}">Explore loan options <span aria-hidden="true">→</span></a></div>
            <div class="hero-proof"><div class="proof-avatars" aria-hidden="true"><span>D</span><span>S</span><span>S</span></div><span>Local support, clear communication</span></div>
        </div>
        <div class="hero-art" aria-label="A simple illustration of steady financial progress">
            <div class="art-sun"></div><div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
            <div class="art-card art-card-top"><span class="art-icon">↗</span><span><small>YOUR NEXT STEP</small><strong>Starts with a conversation</strong></span></div>
            <div class="art-card art-card-bottom"><span class="progress-mark"><i></i><i></i><i></i></span><span><small>THOUGHTFUL PROCESS</small><strong>Apply · Review · Decide</strong></span></div>
            <div class="art-center"><span>DS</span><small>Enriching lives</small></div>
            <div class="art-label"><span>01</span>Support shaped around your plans</div>
        </div>
    </div>
</section>

<section class="trust-strip"><div class="shell trust-inner"><span>Clear next steps</span><span class="trust-divider">✳</span><span>Secure application</span><span class="trust-divider">✳</span><span>Local Nairobi team</span><span class="trust-divider">✳</span><span>Human review</span></div></section>

<section class="section section-products"><div class="shell">
    <div class="section-heading"><div><span class="eyebrow">Find your fit</span><h2>{{ $pageContent['content']['intro_heading'] }}</h2></div><a class="text-link" href="{{ route('products') }}">View all options <span aria-hidden="true">→</span></a></div>
    <div class="product-grid">
        @forelse ($products as $product)
            <article class="product-card"><div class="product-card-top"><span class="product-icon">{{ $loop->first ? '↗' : '↻' }}</span><span class="product-number">0{{ $loop->iteration }}</span></div><h3>{{ $product->name }}</h3><p>{{ $product->description }}</p>@if ($product->minimum_amount || $product->maximum_amount || $product->repayment_months)<div class="product-facts">@if ($product->minimum_amount || $product->maximum_amount)<span>Amount <strong>@if ($product->minimum_amount)KSh {{ number_format((float) $product->minimum_amount, 0) }}@endif @if ($product->minimum_amount && $product->maximum_amount)–@endif @if ($product->maximum_amount)KSh {{ number_format((float) $product->maximum_amount, 0) }}@endif</strong></span>@endif @if ($product->repayment_months)<span>Repayment period <strong>Up to {{ $product->repayment_months }} months</strong></span>@endif</div>@endif<div class="product-rule"></div><div class="product-meta"><span>Terms discussed after assessment</span><a href="{{ route('apply', ['product' => $product->slug]) }}" aria-label="Apply for {{ $product->name }}">↗</a></div></article>
        @empty
            <article class="product-card"><div class="product-card-top"><span class="product-icon">↗</span><span class="product-number">01</span></div><h3>Talk to our team</h3><p>Tell us what you need and we’ll help you understand the options available.</p><div class="product-rule"></div><div class="product-meta"><span>Personal guidance</span><a href="{{ route('contact') }}" aria-label="Contact our team">↗</a></div></article>
        @endforelse
    </div>
    <p class="fine-print">We review each application individually. Any interest, fees, repayment dates and other terms are shared for your review before you decide whether to proceed.</p>
</div></section>

<section class="section process-section"><div class="shell process-grid"><div class="process-intro"><span class="eyebrow eyebrow-light">Simple by design</span><h2>{{ $pageContent['content']['process_heading'] }}</h2><p>{{ $pageContent['content']['process_copy'] }}</p><a class="button button-outline" href="{{ route('apply') }}">Apply in a few steps <span aria-hidden="true">↗</span></a></div><div class="process-steps"><article><span class="step-no">01</span><div><h3>Tell us what you need</h3><p>Share your details and the amount you’re requesting through our secure form.</p></div><span class="step-arrow">↗</span></article><article><span class="step-no">02</span><div><h3>We review together</h3><p>Our team reviews your information and may ask for more details or documents.</p></div><span class="step-arrow">↗</span></article><article><span class="step-no">03</span><div><h3>Review your offer</h3><p>If approved, we explain the full terms so you can make an informed decision.</p></div><span class="step-arrow">↗</span></article></div></div></section>

<section class="section values-section"><div class="shell values-grid"><div><span class="eyebrow">Why Don't Stuck</span><h2>{{ $pageContent['content']['values_heading'] }}</h2><p>{{ $pageContent['content']['values_copy'] }}</p><a class="text-link" href="{{ route('about') }}">Get to know us <span aria-hidden="true">→</span></a></div><div class="values-list"><article><span>01</span><div><h3>Clarity</h3><p>Understand the process and the terms before making a decision.</p></div></article><article><span>02</span><div><h3>Respect</h3><p>Your goals and circumstances deserve a thoughtful conversation.</p></div></article><article><span>03</span><div><h3>Responsibility</h3><p>We assess each request carefully and explain what comes next.</p></div></article></div></div></section>

<section class="contact-cta"><div class="shell cta-inner"><div><span class="eyebrow eyebrow-light">Start a conversation</span><h2>{{ $pageContent['content']['cta_heading'] }}</h2></div><a class="button button-yellow" href="{{ route('apply') }}">Begin your application <span aria-hidden="true">↗</span></a></div></section>
<script type="application/ld+json">{"@@context":"https://schema.org","@type":"FinancialService","name":"Don't Stuck Solutions Limited","telephone":"+254725228380","email":"dontstucksolutions2@gmail.com","address":{"@type":"PostalAddress","postOfficeBoxNumber":"3797-00506","addressLocality":"Nairobi","addressCountry":"KE"},"areaServed":"Kenya"}</script>
@endsection
