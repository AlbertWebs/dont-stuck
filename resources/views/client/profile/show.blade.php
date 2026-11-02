@extends('layouts.client')

@section('title', 'Profile')

@section('content')
    <div class="client-page-heading">
        <div>
            <span class="eyebrow"><span class="eyebrow-dot"></span> PERSONAL DETAILS</span>
            <h1>Your profile</h1>
            <p>Keep your contact and personal information up to date.</p>
        </div>
    </div>

    <section class="client-panel-card client-profile-card">
        <div class="client-panel-heading">
            <div><h2>Account identity</h2><p>Your sign-in email and national ID are protected from direct profile edits.</p></div>
        </div>
        <dl class="client-profile-identity">
            <div><dt>Email address</dt><dd>{{ $borrower->email }}</dd></div>
            <div><dt>National ID</dt><dd>{{ $borrower->national_id }}</dd></div>
        </dl>
    </section>

    <section class="client-panel-card client-profile-card">
        <div class="client-panel-heading">
            <div><h2>Personal information</h2><p>These details are held securely and used to manage your loan applications.</p></div>
        </div>

        <form method="post" action="{{ route('client.profile.update') }}" class="client-profile-form">
            @csrf
            @method('PUT')

            <div class="client-profile-form-grid">
                <label class="client-profile-field">
                    <span>Full name</span>
                    <input name="full_name" type="text" autocomplete="name" maxlength="160" value="{{ old('full_name', $borrower->full_name) }}" required>
                    @error('full_name')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="client-profile-field">
                    <span>Phone number</span>
                    <input name="phone" type="tel" autocomplete="tel" maxlength="32" value="{{ old('phone', $borrower->phone) }}" required>
                    @error('phone')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="client-profile-field client-profile-field-full">
                    <span>Residence</span>
                    <input name="residence" type="text" autocomplete="street-address" maxlength="255" value="{{ old('residence', $borrower->residence) }}">
                    @error('residence')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="client-profile-field">
                    <span>Employer</span>
                    <input name="employer" type="text" autocomplete="organization" maxlength="255" value="{{ old('employer', $borrower->employer) }}">
                    @error('employer')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="client-profile-field">
                    <span>Employer location</span>
                    <input name="employer_location" type="text" maxlength="255" value="{{ old('employer_location', $borrower->employer_location) }}">
                    @error('employer_location')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="client-profile-field">
                    <span>Job title</span>
                    <input name="job_title" type="text" maxlength="160" value="{{ old('job_title', $borrower->job_title) }}">
                    @error('job_title')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="client-profile-field">
                    <span>Net monthly salary (KSh)</span>
                    <input name="net_salary" type="number" min="0" max="9999999999" step="0.01" inputmode="decimal" value="{{ old('net_salary', $borrower->net_salary) }}">
                    @error('net_salary')<small class="field-error">{{ $message }}</small>@enderror
                </label>
            </div>

            <div class="client-profile-section-heading">
                <h3>Next of kin</h3>
                <p>Update the person we may contact if we cannot reach you.</p>
            </div>

            <div class="client-profile-form-grid">
                <label class="client-profile-field">
                    <span>Full name</span>
                    <input name="next_of_kin_name" type="text" maxlength="160" value="{{ old('next_of_kin_name', $borrower->next_of_kin_name) }}">
                    @error('next_of_kin_name')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="client-profile-field">
                    <span>National ID</span>
                    <input name="next_of_kin_id" type="text" maxlength="40" value="{{ old('next_of_kin_id', $borrower->next_of_kin_id) }}">
                    @error('next_of_kin_id')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="client-profile-field">
                    <span>Relationship</span>
                    <input name="next_of_kin_relation" type="text" maxlength="80" value="{{ old('next_of_kin_relation', $borrower->next_of_kin_relation) }}">
                    @error('next_of_kin_relation')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="client-profile-field">
                    <span>Phone number</span>
                    <input name="next_of_kin_phone" type="tel" autocomplete="tel" maxlength="32" value="{{ old('next_of_kin_phone', $borrower->next_of_kin_phone) }}">
                    @error('next_of_kin_phone')<small class="field-error">{{ $message }}</small>@enderror
                </label>
            </div>

            <div class="client-profile-actions">
                <p>Review your information before saving.</p>
                <button class="button button-dark" type="submit">Save changes <span aria-hidden="true">↗</span></button>
            </div>
        </form>
    </section>
@endsection
