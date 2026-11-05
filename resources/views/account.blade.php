@extends('layouts.app')

@section('title', 'Account Settings — Rostrum')

@section('content')

{{--
    The account settings page.

    It is a form, not a report. It answers one question — "how do I
    change the things about this account?" — and it is built around
    that: the account stated once, then the forms that change it.

    Three things were deliberately removed.

    The first is the practice summary. A row of scorecards above a
    form makes the page read as a dashboard, and the numbers there
    were a copy of what the dashboard already says. A settings page
    that reports your statistics is asking for attention it has not
    earned.

    The second is the round-trip through the header. The page used to
    carry a Dashboard link and a New Debate button, both of which the
    rail already offers, one click away and on screen at the same
    time. The rail is the product's navigation; the page is its
    content.

    The third is the list of sections beside the form. The page has
    three panels and each one is titled, so a list of their names is a
    second way of saying what the headings already say. It also took a
    fifth of the width away from the forms on a wide screen, and left a
    column of links that had to be kept in step with the scroll
    position. The panels are now the page's own structure.

    What replaces the summary is the account itself: who you are, when
    the account was made, and the four things you can change about it.

    The fields are the shared form family — the same input the sign-in
    and registration screens use, with the same icons, the same
    show/hide toggle and the same focus ring. A person who has typed a
    password on the sign-in screen is not meeting a new control here.
--}}

<div class="surf surf--settings">

    {{-- ── Header ──────────────────────────────────────────────────────── --}}
    <div class="surf-head">
        <div class="surf-head-body">
            <span class="surf-eyebrow">Account</span>
            <h1 class="surf-title">Settings</h1>
            <p class="surf-subtitle">Your name, your email, your password, and the account itself.</p>
        </div>
    </div>

    {{-- ── The account card ────────────────────────────────────────────── --}}
    {{-- Who you are, stated once. The name and the email are the two
         facts that follow you everywhere in the product, so they are
         given the top of the page before anything that can change
         them.

         The rail already carries Sign Out, so this card does not repeat
         it — two copies of the same action on one screen is one too
         many. What it says instead is when the account was opened, which
         is a fact about the account rather than a measurement of it. --}}
    <div class="surf-account-card" style="margin-bottom: 1rem;">
        <span class="surf-identity-avatar" aria-hidden="true">{{ $user->initials() }}</span>

        <div class="surf-account-card-body">
            <h2 class="surf-account-card-name">{{ $user->name }}</h2>
            <p class="surf-account-card-email">{{ $user->email }}</p>
        </div>

        <div class="surf-account-card-actions">
            <span class="surf-account-card-meta">
                <span class="surf-account-card-meta-label">Member since</span>
                <span class="surf-account-card-meta-value">{{ $user->created_at?->format('F Y') ?? '—' }}</span>
            </span>
        </div>
    </div>

    {{-- ── The sections ────────────────────────────────────────────────── --}}
    {{-- One column of panels. Each panel is titled, so the page can be
         scanned top to bottom without a table of contents beside it. --}}
    <div class="surf-settings">

        {{-- 1 · Profile. --}}
        <section class="surf-settings-panel" id="profile" aria-labelledby="profile-heading">
            <div class="surf-settings-panel-head">
                <div>
                    <h2 class="surf-settings-panel-title" id="profile-heading">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Profile
                    </h2>
                    <p class="surf-settings-panel-meta">How you appear across Rostrum, and where your reports are addressed.</p>
                </div>
            </div>

            <div class="surf-settings-panel-body">
                <form class="surf-form" method="POST" action="{{ route('account.update') }}">
                    @csrf
                    @method('PATCH')

                    {{-- The two fields are the same kind of field and are
                         given the same width. --}}
                    <div class="surf-field-row">
                        <x-form-text-field
                            id="acct-name"
                            name="name"
                            label="Display name"
                            icon="user"
                            type="text"
                            autocomplete="name"
                            :value="old('name', $user->name)"
                        />

                        <x-form-text-field
                            id="acct-email"
                            name="email"
                            label="Email address"
                            icon="mail"
                            type="email"
                            autocomplete="email"
                            :value="old('email', $user->email)"
                        />
                    </div>

                    <div class="surf-form-actions">
                        <button type="submit" class="surf-btn surf-btn-primary">Save Changes</button>
                        <span class="surf-form-note">Takes effect the next time you sign in.</span>
                    </div>
                </form>
            </div>
        </section>

        {{-- 2 · Password. --}}
        {{-- The policy is stated in full, in the same sentence the
             server enforces, and the checklist below it shows the
             four rules one by one as they are met. --}}
        <section class="surf-settings-panel" id="password" aria-labelledby="password-heading">
            <div class="surf-settings-panel-head">
                <div>
                    <h2 class="surf-settings-panel-title" id="password-heading">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Password
                    </h2>
                    <p class="surf-settings-panel-meta">Used to sign in to your account.</p>
                </div>
            </div>

            <div class="surf-settings-panel-body">
                <form class="surf-form" method="POST" action="{{ route('account.password.update') }}"
                      x-data="passwordPolicy()">
                    @csrf
                    @method('PATCH')

                    <x-form-password-field
                        id="current_password"
                        name="current_password"
                        label="Current password"
                        autocomplete="current-password"
                    />

                    <div class="surf-field-row">
                        <x-form-password-field
                            id="new_password"
                            name="password"
                            label="New password"
                            model="password"
                            reveal="showPassword"
                            autocomplete="new-password"
                            hint="At least 10 characters, with an upper case letter, a lower case letter and a digit."
                            describedby="new-password-policy"
                        />

                        <x-form-password-field
                            id="new_password_confirmation"
                            name="password_confirmation"
                            label="Confirm new password"
                            model="confirmation"
                            reveal="showConfirmation"
                            autocomplete="new-password"
                            describedby="new-password-status"
                        >
                            {{-- Live confirmation feedback, so a mismatch is
                                 caught before submitting. The status element
                                 is always in the DOM and always referenced by
                                 the input, so a screen reader hears the change
                                 the moment it happens. --}}
                            <span class="form-status"
                                  id="new-password-status"
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
                    </div>

                    {{-- The policy, in the exact sentence the server
                         enforces, and the four rules beneath it. The
                         checklist is the same component the
                         registration form uses, so the two screens
                         cannot disagree about what the policy is. --}}
                    <x-auth-password-requirements id="new-password-policy" :policy="false" :compact="true" />

                    <div class="surf-form-actions" style="margin-top: 1.25rem;">
                        <button type="submit" class="surf-btn surf-btn-primary">Update Password</button>
                        <span class="surf-form-note">You stay signed in on this device.</span>
                    </div>
                </form>
            </div>
        </section>

        {{-- 3 · The account itself. --}}
        <section class="surf-settings-panel surf-settings-panel--danger" id="danger-zone" aria-labelledby="danger-heading">
            <div class="surf-settings-panel-head">
                <div>
                    <h2 class="surf-settings-panel-title" id="danger-heading">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                        Danger Zone
                    </h2>
                    <p class="surf-settings-panel-meta">Deleting your account is permanent.</p>
                </div>
            </div>

            <div class="surf-settings-panel-body">
                <p class="surf-prose" style="margin-bottom: 1.1rem;">
                    Deleting your account permanently removes your profile and every debate,
                    report and transcript attached to it. This action cannot be undone.
                </p>

                <form action="{{ route('account.destroy') }}" method="POST"
                      x-data="{}"
                      x-ref="deleteAccountForm"
                      @submit.prevent="confirmAction({
                          title: 'Delete Account',
                          message: 'Are you sure you want to permanently delete your account? All your debates and reports will be removed. This cannot be undone.',
                          confirmText: 'Delete Account'
                      }).then(() => $refs.deleteAccountForm.submit())">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="surf-btn surf-btn-danger">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        Delete Account
                    </button>
                </form>
            </div>
        </section>

    </div>

</div>

@endsection
