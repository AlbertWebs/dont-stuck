<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', "Don't Stuck Solutions") | Don't Stuck Solutions</title>
    <meta name="description" content="@yield('description', 'Practical lending conversations and clear application support from Don’t Stuck Solutions in Nairobi.')">
    <meta name="theme-color" content="#f8f7f2">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', "Don't Stuck Solutions") | Don't Stuck Solutions">
    <meta property="og:description" content="@yield('description', 'Practical lending conversations and clear application support from Don’t Stuck Solutions in Nairobi.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="site-body">
    <a class="skip-link" href="#main">Skip to content</a>
    <header class="site-header">
        <div class="shell header-inner">
            <a class="brand" href="{{ route('home') }}" aria-label="Don't Stuck Solutions home">
                <span class="brand-icon-frame" aria-hidden="true"><img class="brand-icon" src="{{ asset('images/dss-emblem.png') }}" alt="" width="1774" height="887"></span>
                <span class="brand-copy"><small>ENRICHING LIVES</small><strong>DON'T STUCK SOLUTIONS</strong></span>
            </a>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-navigation" data-menu-toggle>Menu <span aria-hidden="true">☰</span></button>
            <nav class="site-nav" id="site-navigation" data-menu>
                <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
                <a href="{{ route('products') }}" @if(request()->routeIs('products')) aria-current="page" @endif>Loans</a>
                <a href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>About</a>
                <a href="{{ route('contact') }}" @if(request()->routeIs('contact')) aria-current="page" @endif>Contact</a>
                <a href="{{ route('client.login') }}" @if(request()->routeIs('client.login')) aria-current="page" @endif>Client portal</a>
                <a class="nav-apply" href="{{ route('apply') }}">Apply now <span aria-hidden="true">↗</span></a>
                <button class="theme-toggle" type="button" aria-label="Toggle dark mode" data-theme-toggle>◐</button>
            </nav>
        </div>
    </header>
    <main id="main">
        @if (session('success'))
            <div class="shell"><div class="notice notice-success" role="status">{{ session('success') }}</div></div>
        @endif
        @if ($errors->any())
            <div class="shell"><div class="notice notice-error" role="alert">Please check the highlighted fields and try again.</div></div>
        @endif
        @yield('content')
    </main>
    <footer class="site-footer">
        <div class="shell footer-grid">
            <div>
                <a class="brand brand-footer" href="{{ route('home') }}" aria-label="Don't Stuck Solutions home"><span class="brand-icon-frame" aria-hidden="true"><img class="brand-icon" src="{{ asset('images/dss-emblem.png') }}" alt="" width="1774" height="887"></span><span class="brand-copy"><small>ENRICHING LIVES</small><strong>DON'T STUCK SOLUTIONS</strong></span></a>
                <p>Enriching lives through thoughtful financial support.</p>
            </div>
            <div><h2>Explore</h2><a href="{{ route('products') }}">Loan products</a><a href="{{ route('about') }}">About us</a><a href="{{ route('apply') }}">Apply for a loan</a></div>
            <div><h2>Get in touch</h2><a href="tel:{{ preg_replace('/[^+0-9]/', '', $siteContact['phone']) }}">{{ $siteContact['phone'] }}</a><a href="mailto:{{ $siteContact['email'] }}">{{ $siteContact['email'] }}</a><p>{{ $siteContact['postal_address'] }}</p></div>
        </div>
        <div class="shell footer-bottom"><span>© {{ now()->year }} Don't Stuck Solutions Limited</span><a href="{{ route('client.login') }}">Client portal</a><a href="{{ route('login') }}">Staff sign in</a><span>All applications are assessed before terms are offered.</span></div>
    </footer>
</body>
</html>
