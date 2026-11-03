<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Workspace') · DSS Admin</title>
    <meta name="theme-color" content="#f4f5f2">
    @vite(['resources/css/app.css', 'resources/css/admin-overrides.css', 'resources/js/app.js'])
</head>
<body class="admin-body">
    <a class="skip-link" href="#admin-main">Skip to content</a>
    <div class="admin-shell">
        <aside class="admin-sidebar" data-admin-sidebar>
            <a class="admin-brand" href="{{ route('admin.dashboard') }}"><span class="admin-brand-mark">DS</span><span><strong>DON'T STUCK</strong><small>LOAN WORKSPACE</small></span></a>
            <div class="sidebar-label">WORKSPACE</div>
            <nav class="admin-nav" aria-label="Main navigation">
                <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) class="is-active" @endif><span>◫</span>Overview</a>
                <a href="{{ route('admin.applications.index') }}" @if(request()->routeIs('admin.applications.*')) class="is-active" @endif><span>▤</span>Applications</a>
                <a href="{{ route('admin.borrowers.index') }}" @if(request()->routeIs('admin.borrowers.*')) class="is-active" @endif><span>♙</span>Borrowers</a>
                <a href="{{ route('admin.loans.index') }}" @if(request()->routeIs('admin.loans.*')) class="is-active" @endif><span>◉</span>Active loans</a>
                <a href="{{ route('admin.repayments.index') }}" @if(request()->routeIs('admin.repayments.*')) class="is-active" @endif><span>↙</span>Repayments</a>
                <a href="{{ route('admin.overdue.index') }}" @if(request()->routeIs('admin.overdue.*')) class="is-active" @endif><span>◷</span>Overdue loans</a>
                @if (auth()->user()->role === 'administrator')<a href="{{ route('admin.products.index') }}" @if(request()->routeIs('admin.products.*')) class="is-active" @endif><span>▦</span>Loan products</a>@endif
                @if (auth()->user()->role === 'administrator')<a href="{{ route('admin.loan-terms.edit') }}" @if(request()->routeIs('admin.loan-terms.*')) class="is-active" @endif><span>⚙</span>System loan terms</a>@endif
                @if (auth()->user()->role === 'administrator')<a href="{{ route('admin.website-pages.index') }}" @if(request()->routeIs('admin.website-pages.*')) class="is-active" @endif><span>▧</span>Website content</a>@endif
                @if (auth()->user()->role === 'administrator')<a href="{{ route('admin.users.index') }}" @if(request()->routeIs('admin.users.*')) class="is-active" @endif><span>♧</span>Users & roles</a>@endif
                <a href="{{ route('admin.reports') }}" @if(request()->routeIs('admin.reports*')) class="is-active" @endif><span>▥</span>Reports</a>
            </nav>
            <div class="sidebar-bottom"><div class="sidebar-label">ACCOUNT</div><a class="public-site-link" href="{{ route('admin.profile.edit') }}" @if(request()->routeIs('admin.profile.*')) aria-current="page" @endif><span>♙</span>My profile</a><div class="sidebar-label">COMPANY</div><a class="public-site-link" href="{{ route('home') }}" target="_blank" rel="noopener"><span>↗</span>Public website</a><div class="sidebar-user"><span class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span class="sidebar-user-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ str_replace('_', ' ', ucfirst(auth()->user()->role)) }}</small></span><form method="post" action="{{ route('logout') }}">@csrf<button type="submit" title="Sign out" aria-label="Sign out">⇥</button></form></div></div>
        </aside>
        <div class="admin-main-wrap">
            <header class="admin-topbar"><button class="admin-menu-toggle" type="button" data-admin-menu-toggle aria-label="Open navigation">☰</button><div class="admin-breadcrumb"><span>Workspace</span><span aria-hidden="true">/</span><strong>@yield('title', 'Overview')</strong></div><div class="topbar-right"><span class="topbar-date">{{ now()->format('D, d M Y') }}</span><a class="admin-profile-shortcut" href="{{ route('admin.profile.edit') }}">My profile</a><button class="theme-toggle" type="button" aria-label="Toggle dark mode" data-theme-toggle>◐</button><a class="admin-avatar-link" href="{{ route('admin.profile.edit') }}" aria-label="Edit your admin profile" title="Edit profile"><span class="admin-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span></a></div></header>
            <main class="admin-content" id="admin-main">
                @if (session('success'))<div class="notice notice-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())<div class="notice notice-error" role="alert">Please check your entries and try again.</div>@endif
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
