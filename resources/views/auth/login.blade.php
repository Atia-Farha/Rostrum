@extends('layouts.app')

@section('title', 'Sign In — Rostrum')

@section('content')

<x-auth-shell title="Welcome back" eyebrow="Sign in">
    @section('description')
        Sign in to reach your debate records, transcripts and adjudication reports.
    @endsection

    {{-- A form-level failure (wrong credentials, lockout) belongs in a banner:
         it applies to the submission as a whole, not to one input. --}}
    @if ($errors->any())
        <div class="auth-alert" role="alert">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="auth-form">
        @csrf

        <x-form-text-field
            id="email"
            name="email"
            label="Email address"
            icon="mail"
            type="email"
            autocomplete="email"
            :value="old('email')"
            placeholder="you@example.com"
            autofocus
        />

        <x-form-password-field
            id="password"
            name="password"
            label="Password"
            autocomplete="current-password"
            placeholder="Enter your password"
        />

        <div class="auth-options">
            <label class="auth-check" for="remember">
                <input type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))>
                <span>Keep me signed in</span>
            </label>
        </div>

        <button type="submit" class="auth-submit">
            Sign In
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
    </form>

    <div class="auth-alt">
        Don't have an account?
        <a href="{{ route('register') }}">Create one</a>
    </div>

    @if (config('debate.seed_account_email'))
        <x-demo-account :show-fill-button="true" />
    @endif

</x-auth-shell>

@endsection
