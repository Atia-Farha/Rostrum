@extends('layouts.app')

@section('title', 'Create Account — Rostrum')

@section('content')

<x-auth-shell title="Create your account" eyebrow="Get started">
    @section('description')
        One account keeps every debate, transcript and report in reach.
    @endsection

    {{-- A form-level failure (e.g. the throttle tripped) belongs in a banner. --}}
    @if ($errors->any() && ! $errors->has('name') && ! $errors->has('email') && ! $errors->has('password') && ! $errors->has('password_confirmation'))
        <div class="auth-alert" role="alert">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    {{--
        One Alpine scope for the whole form, so the password and confirmation
        fields can talk to each other and so the submit button can react to
        the form's own state.
    --}}
    <form method="POST"
          action="{{ route('register.store') }}"
          class="auth-form"
          x-data="passwordPolicy()">
        @csrf

        {{-- ── Display name ── --}}
        <x-form-text-field
            id="name"
            name="name"
            label="Display name"
            label-note="Shown on reports"
            icon="user"
            type="text"
            autocomplete="name"
            :value="old('name')"
            placeholder="How should we address you?"
            autofocus
        />

        {{-- ── Email ── --}}
        <x-form-text-field
            id="email"
            name="email"
            label="Email address"
            icon="mail"
            type="email"
            autocomplete="email"
            :value="old('email')"
            placeholder="you@example.com"
        />

        {{-- ── Password ──
             The requirements sit directly under the input, before the
             confirm field, so the person reads the rules in the order they
             will be asked to meet them. --}}
        <x-form-password-field
            id="password"
            name="password"
            label="Password"
            model="password"
            reveal="showPassword"
            autocomplete="new-password"
            placeholder="Create a strong password"
            describedby="password-policy-sentence"
        >
            {{-- The requirements are always visible: a rule that only appears
                 after a mistake is a rule the person already broke. --}}
            <x-auth-password-requirements id="password-policy" />
        </x-form-password-field>

        {{-- ── Confirm password ── --}}
        <x-form-password-field
            id="password_confirmation"
            name="password_confirmation"
            label="Confirm password"
            model="confirmation"
            reveal="showConfirmation"
            autocomplete="new-password"
            placeholder="Repeat your password"
            describedby="password_confirmation-status"
        >
            {{-- Live confirmation feedback, so a mismatch is caught before
                 submitting. The status element is always in the DOM and
                 always referenced by the input, so a screen reader hears the
                 change the moment it happens. --}}
            <span class="form-status"
                  id="password_confirmation-status"
                  role="status"
                  aria-live="polite">
                <span class="form-status-icon form-status-icon--error" x-show="confirmation.length > 0 && confirmation !== password" x-cloak aria-hidden="true">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </span>
                <span class="form-status-icon form-status-icon--ok" x-show="confirmation.length > 0 && confirmation === password" x-cloak aria-hidden="true">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </span>
                <span class="form-status-text" x-show="confirmation.length > 0 && confirmation !== password" x-cloak>Passwords do not match.</span>
                <span class="form-status-text" x-show="confirmation.length > 0 && confirmation === password" x-cloak>Passwords match.</span>
            </span>
        </x-form-password-field>

        <label class="auth-check" for="remember">
            <input type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))>
            <span>Keep me signed in</span>
        </label>

        {{-- The button stays clickable. A disabled button with no
             explanation of why is a dead end — the checklist above already
             says what is missing, and the browser's own required-field
             checks catch the rest. The server validates regardless. --}}
        <button type="submit" class="auth-submit">
            Create Account
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
    </form>

    <p class="auth-legal">
        Your debates stay private to your account.
    </p>

    <div class="auth-alt">
        Already have an account?
        <a href="{{ route('login') }}">Sign in</a>
    </div>

    {{-- The demo panel sits below the form: the account form is the reason
         someone opened this page, so it must not be pushed down by it. --}}
    @if (config('debate.seed_account_email'))
        <x-demo-account :show-login-link="true" />
    @endif

</x-auth-shell>

{{--
    The live password feedback runs on the shared `passwordPolicy()`
    scope in resources/js/app.js, which the settings page uses too. It
    is defined in one place so the policy it shows and the policy the
    server enforces cannot drift apart.
--}}

@endsection
