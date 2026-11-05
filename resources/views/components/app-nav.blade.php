{{--
    The application shell.

    Signed-in pages get a fixed sidebar: a brand band, the one action the
    product exists for, then the destinations as grouped links, with the
    identity pinned to the foot of the rail.

    The structure follows what a workspace rail is for, in order:

        1. the brand          — which product this is
        2. the primary action — the thing you came here to do
        3. the destinations   — where you can go, grouped by purpose
        4. the identity       — who you are, and how you leave

    A vertical rail keeps those destinations grouped and scannable —
    spread across a wide top bar they read as loose links rather than
    navigation.

    The public landing page keeps the horizontal bar, and the debate arena
    keeps its own compact strip (see layouts/app.blade.php).
--}}
@php
    // The account block is the same on both shells, so it is rendered
    // once here and placed by the caller.
    $currentUser = auth()->user();

    // Rail icons. 16px at a 2px stroke reads at a glance without
    // competing with the labels beside them.
    $iconOverview = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>';

    $iconHistory = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15.5 14"/></svg>';

    $iconSettings = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';

    $iconSignOut = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>';

    $iconPlus = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>';

    // Two groups, not five headings. Each group answers one question —
    // "what am I working on" and "what is mine" — so a section label
    // never introduces a single link.
    //
    // The labels match the words the product already uses elsewhere:
    // the landing page's dashboard button, the account page's own button
    // and the dashboard's eyebrow all call it "Dashboard".
    $navGroups = [
        'Workspace' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => $iconOverview],
            ['label' => 'History', 'route' => 'history.index', 'icon' => $iconHistory],
        ],
        'Account' => [
            ['label' => 'Settings', 'route' => 'account.index', 'icon' => $iconSettings],
        ],
    ];

    $isSetup = request()->routeIs('setup');
@endphp

{{-- ── Sidebar ──────────────────────────────────────────────────────── --}}
{{-- The scrim sits outside the rail so it dims the page behind rather
     than the rail itself. --}}
<div class="app-side-scrim" x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" aria-hidden="true"></div>

<aside class="app-side" aria-label="Workspace" @keydown.escape.window="sidebarOpen = false">
    <div class="app-side-inner">

        {{-- 1 · Brand band ------------------------------------------------ --}}
        <div class="app-side-brand">
            <a href="{{ route('home') }}" class="app-side-brand-link">
                <span class="app-side-brand-mark" aria-hidden="true"></span>
                <span class="app-side-brand-name">Rostrum</span>
            </a>

            {{-- The rail is always on screen on wide viewports, so its
                 close button has nothing to close there. --}}
            <button type="button"
                    class="app-side-close"
                    @click="sidebarOpen = false"
                    aria-label="Close navigation">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        {{-- 2 + 3 · Primary action and destinations ------------------------- --}}
        <div class="app-side-body">

            <nav class="app-side-nav" aria-label="Primary">
                {{-- Starting a debate is what the product is for, so it is a
                     button at the top of the rail rather than one more link
                     in the list below it. The shortcut badge is honest: the
                     key is wired up in resources/js/app.js. --}}
                <a href="{{ route('setup') }}"
                   class="surf-btn surf-btn-primary app-side-cta {{ $isSetup ? 'is-active' : '' }}"
                   data-nav-shortcut="n"
                   @click="sidebarOpen = false"
                   @if ($isSetup) aria-current="page" @endif>
                    {!! $iconPlus !!}
                    <span>New Debate</span>
                    <kbd class="app-side-kbd" aria-hidden="true">N</kbd>
                </a>

                @hasSection('nav_actions')
                    <div class="app-side-actions">
                        @yield('nav_actions')
                    </div>
                @endif

                @foreach ($navGroups as $groupLabel => $groupItems)
                    {{-- Each group is named for screen readers as well as
                         for the eye — the label is not decoration. --}}
                    <div class="app-side-group" role="group" aria-labelledby="app-side-group-{{ $loop->index }}">
                        <span class="app-side-label" id="app-side-group-{{ $loop->index }}">{{ $groupLabel }}</span>

                        @foreach ($groupItems as $item)
                            <a href="{{ route($item['route']) }}"
                               class="app-side-link {{ request()->routeIs($item['route']) ? 'is-active' : '' }}"
                               @click="sidebarOpen = false"
                               @if (request()->routeIs($item['route'])) aria-current="page" @endif>
                                {!! $item['icon'] !!}
                                <span class="app-side-text">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach

                {{-- Signing out lives in the rail rather than in a popover.
                     A menu that held one button was a click for nothing,
                     and the destinations it used to repeat are already
                     listed directly above it. It sits below a rule, so
                     ending the session reads as a different act from
                     moving around the product. --}}
                <div class="app-side-group app-side-group--session">
                    <hr class="app-side-sep">

                    <form action="{{ route('logout') }}" method="POST" class="app-side-form">
                        @csrf
                        <button type="submit" class="app-side-link is-danger" @click="sidebarOpen = false">
                            {!! $iconSignOut !!}
                            <span class="app-side-text">Sign Out</span>
                        </button>
                    </form>
                </div>
            </nav>
        </div>

        {{-- 4 · Identity ---------------------------------------------------- --}}
        {{-- The account block states who you are. It sits directly under
             the destinations rather than pinned to the foot of the rail:
             a fixed foot left a tall empty gap below the last link, and
             on a phone that gap read as clutter rather than breathing
             room. --}}
        @auth
            <div class="app-side-user">
                <span class="app-side-user-avatar" aria-hidden="true">{{ $currentUser->initials() }}</span>
                <span class="app-side-user-body">
                    <span class="app-side-user-name">{{ $currentUser->name }}</span>
                    <span class="app-side-user-email">{{ $currentUser->email }}</span>
                </span>
            </div>
        @else
            <div class="app-side-user">
                <div class="app-side-guest">
                    <a href="{{ route('login') }}" class="surf-btn surf-btn-outline surf-btn-sm">Sign In</a>
                    <a href="{{ route('register') }}" class="surf-btn surf-btn-primary surf-btn-sm">Create Account</a>
                </div>
            </div>
        @endauth
    </div>
</aside>

{{-- ── Mobile bar ------------------------------------------------------ --}}
{{-- Below the breakpoint the rail becomes a drawer, so the page needs
     a bar of its own to open it and to say which product you are in.
     It is a real bar in the flow rather than a floating button, so it
     never sits on top of the page title.

     The wordmark leads on the left and the menu button sits on the
     right, which is where a thumb reaches for it. --}}
<div class="app-side-bar">
    <a href="{{ route('home') }}" class="app-side-bar-brand">
        <span class="app-side-brand-mark" aria-hidden="true"></span>
        Rostrum
    </a>

    <button type="button"
            class="app-side-open"
            @click="sidebarOpen = true"
            aria-label="Open navigation"
            :aria-expanded="sidebarOpen">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </button>
</div>
