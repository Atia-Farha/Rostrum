{{--
    Auth shell — the shared two-column layout for /login and /register.

    Left column (desktop only): a quiet brand rail that explains what the
    product does, so the credential form is not the only thing on screen.
    The wordmark sits at the top of this rail.

    Right column: the form itself, in a readable 25rem measure.

    On mobile the whole rail is hidden, and the wordmark moves above the
    form, top-left — the only thing carried over from the left side.

    Usage:
        <x-auth-shell title="Welcome back" eyebrow="Sign in">
            <x-slot:description>...</x-slot:description>
            ...form fields...
        </x-auth-shell>
--}}
@props([
    'eyebrow' => null,
    'title',
    'heading' => 'h1',
])

<div class="auth-shell">

    {{-- ── Brand rail (desktop only) ── --}}
    {{-- The rail itself is NOT aria-hidden: the wordmark at its top is a real
         link back to the landing page, and there is no site nav on these
         screens. Only the decorative copy below it is hidden from assistive
         technology. --}}
    <aside class="auth-brand">
        {{-- The wordmark lives at the top of the rail on desktop. Without
             the site nav there is no other way back to the landing page. --}}
        <a href="{{ route('home') }}" class="auth-brand-logo">
            <span class="auth-brand-mark" aria-hidden="true"></span>
            Rostrum
        </a>

        <div class="auth-brand-copy" aria-hidden="true">
            <span class="auth-brand-eyebrow">AI Debate Practice</span>

            <h2 class="auth-brand-title">
                Debate sharper.<br>
                <em>Score higher.</em>
            </h2>

            <p class="auth-brand-sub">
                Run full structured debates against an AI opponent, then get a formal
                adjudication report scored on Matter, Manner and Method.
            </p>

            <ul class="auth-brand-list">
                <li class="auth-brand-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>Three-phase sessions — opening, rebuttal and closing speeches</span>
                </li>
                <li class="auth-brand-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>A 100-point rubric, the same criteria used in championships</span>
                </li>
                <li class="auth-brand-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>Practise in English or Bangla, against three AI personas</span>
                </li>
            </ul>
        </div>

        <p class="auth-brand-foot" aria-hidden="true">
            Every session is saved to your account with its transcript and report.
        </p>
    </aside>

    {{-- ── Form column ──
         This is a <section>, not a <main>: the layout already wraps the page
         in <main id="main-content">, and a page may only have one main
         landmark. --}}
    <section class="auth-panel" aria-label="{{ $title }}">
        <div class="auth-panel-inner">

            {{-- The wordmark, shown only where the brand rail is not. It is
                 the screen's own way back to the landing page. --}}
            <a href="{{ route('home') }}" class="auth-home-link">
                <span class="auth-brand-mark" aria-hidden="true"></span>
                Rostrum
            </a>

            <div class="auth-head">
                @if ($eyebrow)
                    <span class="auth-eyebrow">{{ $eyebrow }}</span>
                @endif

                <{{ $heading }} class="auth-title">{{ $title }}</{{ $heading }}>

                @hasSection('description')
                    <p class="auth-sub">@yield('description')</p>
                @endif
            </div>

            {{ $slot }}
        </div>
    </section>

</div>
