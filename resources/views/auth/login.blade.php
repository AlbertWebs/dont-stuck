@extends('layouts.site')

@section('title', 'Staff sign in')
@section('content')
<section class="section login-section"><form class="login-card" method="post" action="{{ route('login.store') }}">@csrf<div class="login-mark">DS</div><span class="eyebrow">Staff portal</span><h1>Welcome back.</h1><p>Sign in to your Don’t Stuck Solutions workspace.</p><div class="field"><label for="email">Work email</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>@error('email')<small class="field-error">{{ $message }}</small>@enderror</div><div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required>@error('password')<small class="field-error">{{ $message }}</small>@enderror</div><label class="check-row"><input type="checkbox" name="remember" value="1"><span>Remember me on this device</span></label><button class="button button-dark button-full" type="submit">Sign in <span aria-hidden="true">↗</span></button><a class="login-back" href="{{ route('home') }}">← Back to public website</a></form></section>
@endsection
