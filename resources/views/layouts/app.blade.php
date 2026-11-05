<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Rostrum')</title>
    <meta name="description" content="@yield('meta_description', 'Practice competitive debate against an AI opponent in English or Bangla. Get expert adjudication and argument coaching.')">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">
    @yield('head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{--
        Alpine is bundled locally via Vite (fast, no runtime CDN dependency).
        If the bundle ever fails to load, Alpine is loaded from the CDN as a
        safety net so the app can never lose interactivity.
    --}}
    <script>
        window.deferLoadingAlpine = function () {};
        window.addEventListener('DOMContentLoaded', function () {
            setTimeout(function () {
                if (window.Alpine) return;
                var s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js';
                s.onload = function () { window.Alpine.start(); };
                document.head.appendChild(s);
            }, 1500);
        });
    </script>
</head>
@php
    // The credential screens render full-height and carry their own brand
    // treatment, so they opt out of the site nav entirely rather than
    // sitting underneath a fixed bar.
    $hideNav = view()->shared('hideNav', false);

    // The landing page keeps the nav but drops the four dashboard links:
    // its signed-in visitor reaches those pages through the rail on every
    // working page, so the nav would otherwise repeat them.
    $hideDashboardLinks = view()->shared('hideDashboardLinks', false);

    // The signed-in workspace gets a sidebar. The landing page keeps a
    // horizontal bar — it is a marketing surface, not a workspace, and
    // a rail there would be a false affordance.
    //
    // The debate arena is part of that workspace, so it takes the rail
    // too. It used to opt out and carry a strip of its own, which left
    // the one page you cannot leave mid-round with no way out of it —
    // and no navigation at all on a phone, where the rail is the only
    // navigation there is.
    $hasSidebar = ! $hideNav && ! ($hideDashboardLinks ?? false);
@endphp

<body class="h-full app-shell @yield('body_class') @if ($hideNav) is-auth @endif @if ($hasSidebar) has-side @endif"
      x-data="{ sidebarOpen: false }"
      x-init="$watch('sidebarOpen', (isOpen) => document.body.classList.toggle('sidebar-is-open', isOpen))">

    <a href="#main-content" class="skip-link">Skip to main content</a>

    @unless ($hideNav)
        @if ($hasSidebar)
            @include('components.app-nav')
        @else
            {{-- The landing page keeps the horizontal bar. --}}
            <nav class="app-nav" aria-label="Primary" x-data="{ open: false }">
                <a href="{{ route('home') }}" class="app-nav-brand">
                    <span class="app-nav-mark" aria-hidden="true"></span>
                    Rostrum
                </a>

                <span class="app-nav-divider" aria-hidden="true"></span>

                <div class="app-nav-actions">
                    @hasSection('nav_actions')
                        @yield('nav_actions')
                    @endif

                    @auth
                        {{-- Signed in, the landing page's nav offers one thing
                             only: the way back into the product. The account
                             pill that used to sit here was a menu of five
                             links — a popover to open before you could even
                             find the dashboard — on a marketing page whose
                             whole job is to show what the product is. One
                             button says it outright.

                             The identity and the other destinations are not
                             lost: the rail carries them on every working
                             page, and the landing page's own footer still
                             lists them. --}}
                        <a href="{{ route('dashboard') }}" class="surf-btn surf-btn-primary surf-btn-sm app-nav-dashboard">
                            Go to Dashboard
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="surf-btn surf-btn-outline surf-btn-sm">Sign In</a>
                        <a href="{{ route('register') }}" class="surf-btn surf-btn-primary surf-btn-sm">Create Account</a>
                    @endauth
                </div>
            </nav>
        @endif
    @endunless

    {{-- Global Toast Notification System --}}
    <div x-data="toastSystem()"
         @show-toast.window="add($event.detail)"
         class="toast-container"
         style="position: fixed !important; bottom: 1.5rem !important; right: 1.5rem !important; top: auto !important; left: auto !important; z-index: 99999 !important; display: flex !important; flex-direction: column !important; align-items: flex-end !important; gap: 0.6rem !important; max-width: 380px !important; width: calc(100% - 3rem) !important; pointer-events: none !important;">

        @if(session('error'))
            <div x-init="add({ type: 'error', message: @js(session('error')) })"></div>
        @endif
        @if(session('success'))
            <div x-init="add({ type: 'success', message: @js(session('success')) })"></div>
        @endif

        <template x-for="toast in toasts" :key="toast.id">
            <div class="toast-item"
                 :class="toast.type"
                 style="pointer-events: auto !important; width: 100% !important; padding: 0.85rem 1.1rem !important; border-radius: 6px !important; background: #0c101b !important; border: 1px solid #1e2535 !important; color: #f0f4ff !important; font-size: 0.85rem !important; display: flex !important; align-items: center !important; justify-content: space-between !important; gap: 0.75rem !important; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5) !important; transform: translateY(0); transition: all 0.25s ease;"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-2">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <template x-if="toast.type === 'success'">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </template>
                    <template x-if="toast.type === 'error'">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </template>
                    <template x-if="toast.type === 'info'">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    </template>
                    <span x-text="toast.message" style="line-height: 1.4;"></span>
                </div>
                <button type="button" @click="remove(toast.id)" aria-label="Close notification" style="background: none; border: none; color: #5c7090; cursor: pointer; padding: 2px 4px; font-size: 1.2rem; line-height: 1; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">×</button>
            </div>
        </template>
    </div>

    {{-- Global Confirmation Modal --}}
    <div x-data="confirmModalSystem()"
         @confirm-action.window="open($event.detail)"
         x-show="show" x-cloak
         style="position: fixed !important; top: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important; z-index: 10000 !important; margin: 0 !important; padding: 0 !important; pointer-events: auto !important;">
        <div x-show="show" x-transition.opacity @click="cancel()" style="position: fixed !important; top: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important; background: rgba(5, 7, 13, 0.8) !important; backdrop-filter: blur(4px) !important; z-index: 10001 !important;"></div>
        <div x-show="show"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             style="position: fixed !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; width: calc(100% - 2.5rem) !important; max-width: 420px !important; background: #0a0d16 !important; border: 1px solid #1e2535 !important; border-radius: 8px !important; padding: 1.5rem !important; box-shadow: 0 20px 40px rgba(0,0,0,0.7) !important; z-index: 10002 !important;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); display: flex; align-items: center; justify-content: center; color: #f87171; flex-shrink: 0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                </div>
                <h3 x-text="title" style="font-size: 1.05rem; font-weight: 700; color: #f0f4ff; margin: 0;"></h3>
            </div>
            <p x-text="message" style="font-size: 0.85rem; color: #8099be; line-height: 1.55; margin-bottom: 1.5rem;"></p>
            <div style="display: flex; justify-content: flex-end; gap: 0.6rem;">
                <button type="button" @click="cancel()" class="surf-btn surf-btn-secondary surf-btn-sm">Cancel</button>
                <button type="button" @click="confirm()" class="surf-btn surf-btn-danger surf-btn-sm" x-text="confirmText"></button>
            </div>
        </div>
    </div>

    <script>
        function toastSystem() {
            return {
                toasts: [],
                add(detail) {
                    const id = Date.now() + Math.random();
                    const toast = {
                        id: id,
                        type: detail.type || 'info',
                        message: detail.message || ''
                    };
                    this.toasts.push(toast);
                    setTimeout(() => this.remove(id), detail.duration || 4000);
                },
                remove(id) {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }
            };
        }

        function confirmModalSystem() {
            return {
                show: false,
                title: 'Confirm Action',
                message: 'Are you sure?',
                confirmText: 'Confirm',
                onConfirm: null,
                init() {
                    this.$watch('show', (val) => {
                        if (val) {
                            document.body.style.overflow = 'hidden';
                        } else {
                            document.body.style.overflow = '';
                        }
                    });
                },
                open(detail) {
                    this.title = detail.title || 'Confirm Action';
                    this.message = detail.message || 'Are you sure you want to proceed?';
                    this.confirmText = detail.confirmText || 'Confirm';
                    this.onConfirm = detail.onConfirm || null;
                    this.show = true;
                },
                confirm() {
                    if (typeof this.onConfirm === 'function') {
                        this.onConfirm();
                    }
                    this.show = false;
                },
                cancel() {
                    this.show = false;
                }
            };
        }

        /**
         * The password policy, as one Alpine scope.
         *
         * The rules here mirror the server-side policy in
         * AccountController::passwordRule() (min 10 chars, letters,
         * mixed case, numbers) so the checklist never promises something
         * the backend will reject.
         *
         * It is defined here, once, because the registration form and
         * the settings page both show the policy. Two copies of those
         * words would be two chances for them to drift apart from the
         * rule the validator actually enforces.
         *
         * The rules themselves are read by the password-requirements
         * component.
         */
        window.passwordPolicy = function () {
            return {
                password: '',
                confirmation: '',
                showPassword: false,
                showConfirmation: false,

                /** The four rules, each one independently checkable. */
                get rules() {
                    return {
                        length: this.password.length >= 10,
                        lower: /[a-z]/.test(this.password),
                        upper: /[A-Z]/.test(this.password),
                        number: /\d/.test(this.password),
                    };
                },

                /** How many of the rules are met. */
                get score() {
                    const r = this.rules;
                    return [r.length, r.lower, r.upper, r.number].filter(Boolean).length;
                },

                /** How many rules there are in total. */
                get total() {
                    return 4;
                },

                /**
                 * Whether the field has been touched yet.
                 *
                 * Before the first keystroke the rules speak for
                 * themselves, so the summary stays neutral and no
                 * per-rule "add one" hints compete with them. Once
                 * there is something to react to, both become specific.
                 */
                get started() {
                    return this.password.length > 0;
                },

                /** The one-line summary, in words, of what is left. */
                get summary() {
                    const met = this.score;
                    const left = this.total - met;

                    if (! this.started) return 'Four requirements to meet';
                    if (met === this.total) return 'All requirements met';
                    if (left === 1) return '1 requirement left';
                    return `${left} requirements left`;
                },

                /** The compact count shown beside the summary. */
                get badgeLabel() {
                    return `${this.score}/${this.total}`;
                },

                /** Whether the confirmation field matches. */
                get matches() {
                    return this.confirmation.length > 0 && this.confirmation === this.password;
                },
            };
        };

        // Global helper for triggers
        window.showToast = function(message, type = 'info') {
            window.dispatchEvent(new CustomEvent('show-toast', { detail: { message, type } }));
        };

        window.confirmAction = function(options) {
            return new Promise((resolve) => {
                window.dispatchEvent(new CustomEvent('confirm-action', {
                    detail: {
                        ...options,
                        onConfirm: () => resolve(true)
                    }
                }));
            });
        };

    </script>

    <main id="main-content" aria-label="Main content">
        @yield('content')
    </main>

</body>
</html>
