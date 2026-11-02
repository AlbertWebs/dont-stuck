@extends('layouts.site')

@section('title', 'Client portal sign in')
@section('description', 'Securely access your loan applications, loan details, documents and profile.')

@section('content')
    <section class="client-auth-section">
        <div class="client-auth-card">
            <span class="eyebrow"><span class="eyebrow-dot"></span> CLIENT PORTAL</span>
            <h1>Sign in to your account</h1>
            <p>We’ll email you a one-time code to securely access your applications and loans.</p>

            @if (session('status'))
                <div class="notice notice-success" role="status">{{ session('status') }}</div>
            @endif

            <form method="post" action="{{ route('client.login.request-code') }}" class="client-auth-form">
                @csrf
                <label for="client-email">Email address</label>
                <input id="client-email" name="email" type="email" value="{{ old('email', session('client_login_email')) }}" autocomplete="email" required>
                @error('email')<span class="field-error">{{ $message }}</span>@enderror
                <button class="button button-dark" type="submit">Email me a sign-in code <span aria-hidden="true">↗</span></button>
            </form>

            <p class="client-auth-switch">New to the client portal? <a href="{{ route('client.register') }}">Create an account</a></p>

            @if (session('client_login_email'))
                <form method="post" action="{{ route('client.login.verify') }}" class="client-auth-form client-verify-form">
                    @csrf
                    <label for="client-code">One-time code</label>
                    <input id="client-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code" required>
                    @error('code')<span class="field-error">{{ $message }}</span>@enderror
                    <button class="button button-yellow" type="submit">Sign in</button>
                    <small>Enter the code sent to {{ session('client_login_email') }}. Codes expire after 10 minutes.</small>
                </form>
            @endif
        </div>
    </section>
@endsection
