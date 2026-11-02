@extends('layouts.site')

@section('title', 'Create a client account')
@section('description', 'Create a secure client portal account to track your applications and loans.')

@section('content')
    <section class="client-auth-section">
        <div class="client-auth-card">
            <span class="eyebrow"><span class="eyebrow-dot"></span> CLIENT PORTAL</span>
            <h1>Create your account</h1>
            <p>Enter your details and we’ll email you a one-time code to verify your address and finish setting up your secure account.</p>

            <form method="post" action="{{ route('client.register.store') }}" class="client-auth-form">
                @csrf
                <label for="client-name">Full name</label>
                <input id="client-name" name="full_name" type="text" value="{{ old('full_name') }}" autocomplete="name" maxlength="160" required>
                @error('full_name')<span class="field-error">{{ $message }}</span>@enderror

                <label for="client-national-id">National ID</label>
                <input id="client-national-id" name="national_id" type="text" value="{{ old('national_id') }}" autocomplete="off" maxlength="40" required>
                @error('national_id')<span class="field-error">{{ $message }}</span>@enderror

                <label for="client-phone">Phone number</label>
                <input id="client-phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" maxlength="32" required>
                @error('phone')<span class="field-error">{{ $message }}</span>@enderror

                <label for="client-register-email">Email address</label>
                <input id="client-register-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required>
                @error('email')<span class="field-error">{{ $message }}</span>@enderror

                <button class="button button-dark" type="submit">Create account and email code <span aria-hidden="true">↗</span></button>
            </form>

            <p class="client-auth-switch">Already have an account? <a href="{{ route('client.login') }}">Sign in</a></p>
        </div>
    </section>
@endsection
