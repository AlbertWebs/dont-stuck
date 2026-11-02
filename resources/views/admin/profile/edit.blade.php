@extends('layouts.admin')

@section('title', 'My profile')

@section('content')
    <div class="admin-page-heading">
        <div>
            <span class="eyebrow">Account settings</span>
            <h1>My profile</h1>
            <p>Update the name and email you use to access the admin workspace.</p>
        </div>
    </div>

    <form method="post" action="{{ route('admin.profile.update') }}" class="admin-profile-form">
        @csrf
        @method('PUT')

        <section class="panel admin-profile-panel">
            <div class="panel-heading">
                <div><h2>Profile details</h2><p>Your email address is used to sign in to the workspace.</p></div>
                <span class="admin-profile-role">{{ str_replace('_', ' ', ucfirst($user->role)) }}</span>
            </div>

            <div class="field-grid admin-profile-fields">
                <div class="field">
                    <label for="profile-name">Full name</label>
                    <input id="profile-name" name="name" type="text" autocomplete="name" maxlength="160" value="{{ old('name', $user->name) }}" required>
                    @error('name')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="profile-email">Email address</label>
                    <input id="profile-email" name="email" type="email" autocomplete="email" maxlength="255" value="{{ old('email', $user->email) }}" required>
                    @error('email')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>
        </section>

        <section class="panel admin-profile-panel">
            <div class="panel-heading">
                <div><h2>Change password</h2><p>Leave these fields empty to keep your current password. A new password must be at least 12 characters.</p></div>
            </div>

            <div class="field-grid admin-profile-fields">
                <div class="field">
                    <label for="current-password">Current password</label>
                    <input id="current-password" name="current_password" type="password" autocomplete="current-password">
                    @error('current_password')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="new-password">New password</label>
                    <input id="new-password" name="password" type="password" autocomplete="new-password" minlength="12">
                    @error('password')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <div class="field">
                    <label for="password-confirmation">Confirm new password</label>
                    <input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12">
                </div>
            </div>

            <div class="admin-profile-actions">
                <p>Changes apply to your admin sign-in account.</p>
                <button class="button button-dark" type="submit">Save profile <span aria-hidden="true">↗</span></button>
            </div>
        </section>
    </form>
@endsection
