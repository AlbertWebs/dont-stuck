<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#18201e">
    <title>@yield('title', 'Dashboard') · Client Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="client-body">
    <a class="skip-link" href="#client-main">Skip to content</a>
    <div class="client-layout">
        <aside class="client-sidebar" data-client-sidebar>
            <a class="client-sidebar-brand" href="{{ route('client.dashboard') }}">
                <span class="client-sidebar-mark">DSS</span>
                <span><strong>CLIENT PORTAL</strong><small>DON'T STUCK SOLUTIONS</small></span>
            </a>
            <span class="client-nav-label">MY ACCOUNT</span>
            <nav class="client-nav" aria-label="Client navigation">
                <a href="{{ route('client.dashboard') }}" @if(request()->routeIs('client.dashboard')) aria-current="page" @endif><span aria-hidden="true">◫</span>Dashboard</a>
                <a href="{{ route('client.applications.index') }}" @if(request()->routeIs('client.applications.*')) aria-current="page" @endif><span aria-hidden="true">▤</span>Applications</a>
                <a href="{{ route('client.loans.index') }}" @if(request()->routeIs('client.loans.*')) aria-current="page" @endif><span aria-hidden="true">◉</span>Loans</a>
                @if ($sidebarActiveLoans->isNotEmpty())
                    <div class="client-nav-loans" aria-label="Active loans">
                        @foreach ($sidebarActiveLoans as $sidebarLoan)
                            <a href="{{ route('client.loans.show', $sidebarLoan) }}" @if(request()->routeIs('client.loans.show') && request()->route('loanAccount')?->is($sidebarLoan)) aria-current="page" @endif>
                                <span class="client-nav-loan-arrow" aria-hidden="true">↳</span>
                                <span class="client-nav-loan-copy"><strong>{{ $sidebarLoan->application?->product?->name ?? 'Loan account' }}</strong><small>{{ $sidebarLoan->reference }}</small></span>
                            </a>
                        @endforeach
                    </div>
                @endif
                <a href="{{ route('client.documents.index') }}" @if(request()->routeIs('client.documents.*')) aria-current="page" @endif><span aria-hidden="true">▧</span>Documents</a>
                <a href="{{ route('client.profile.show') }}" @if(request()->routeIs('client.profile.*')) aria-current="page" @endif><span aria-hidden="true">♙</span>Profile</a>
            </nav>
            <div class="client-sidebar-bottom">
                <div class="client-sidebar-user">
                    <span class="client-user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></span>
                </div>
                <form method="post" action="{{ route('client.logout') }}">
                    @csrf
                    <button type="submit">Sign out <span aria-hidden="true">↗</span></button>
                </form>
            </div>
        </aside>
        <div class="client-main">
            <header class="client-topbar">
                <button class="client-menu-toggle" type="button" aria-label="Open client navigation" aria-expanded="false" data-client-menu-toggle>☰</button>
                <div><span>DON'T STUCK SOLUTIONS</span><strong>@yield('title', 'Dashboard')</strong></div>
                <a href="{{ route('home') }}">Public website <span aria-hidden="true">↗</span></a>
            </header>
            <main class="client-content" id="client-main">
                @if (session('success'))<div class="notice notice-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())<div class="notice notice-error" role="alert">Please check the highlighted fields and try again.</div>@endif
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
