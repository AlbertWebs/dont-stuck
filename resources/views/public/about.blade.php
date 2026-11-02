@extends('layouts.site')

@section('title', $pageContent['meta_title'])
@section('description', $pageContent['meta_description'])

@section('content')
<section class="page-hero"><div class="shell"><span class="eyebrow">About Don't Stuck</span><h1>{{ $pageContent['content']['heading'] }}</h1><p>{{ $pageContent['content']['lead'] }}</p></div></section>
<section class="section"><div class="shell story-grid"><div class="story-aside"><span class="story-mark">DS</span><p>DON'T STUCK<br>SOLUTIONS LIMITED</p></div><div class="story-copy"><span class="eyebrow">Our approach</span><h2>{{ $pageContent['content']['story_heading'] }}</h2><p>{{ $pageContent['content']['story_copy'] }}</p><p>We believe people should have enough information to make a considered choice. That means setting expectations early, protecting the details you share and explaining any proposed terms before you agree.</p><div class="mission-grid"><article><small>OUR MISSION</small><h3>Enriching lives</h3><p>{{ $pageContent['content']['mission'] }}</p></article><article><small>OUR VISION</small><h3>Move forward with confidence</h3><p>{{ $pageContent['content']['vision'] }}</p></article></div></div></div></section>
<section class="section values-section"><div class="shell values-grid"><div><span class="eyebrow">What guides us</span><h2>{{ $pageContent['content']['values_heading'] }}</h2><p>Our values shape how we listen, review and serve.</p></div><div class="values-list"><article><span>01</span><div><h3>Integrity</h3><p>Be honest and direct in every conversation.</p></div></article><article><span>02</span><div><h3>Care</h3><p>Treat each customer and their information with respect.</p></div></article><article><span>03</span><div><h3>Accountability</h3><p>Take responsibility for decisions and follow through.</p></div></article></div></div></section>
<section class="contact-cta"><div class="shell cta-inner"><div><span class="eyebrow eyebrow-light">Get started</span><h2>Tell us what you’re<br>working toward.</h2></div><a class="button button-yellow" href="{{ route('apply') }}">Apply now <span aria-hidden="true">↗</span></a></div></section>
@endsection
