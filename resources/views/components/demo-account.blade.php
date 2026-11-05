@props(['showLoginLink' => false, 'showFillButton' => false])

@php
    $demoEmail    = config('debate.seed_account_email');
    $demoPassword = config('debate.seed_account_password');
@endphp

@if ($demoEmail && $demoPassword)
    {{--
        A compact, collapsible demo-credentials panel.

        It is deliberately understated: a single quiet row that expands on
        demand. The credentials are a convenience for evaluators, not the
        primary call to action, so they must not compete with the form.
    --}}
    <details class="auth-demo" x-data="demoAccount">
        <summary class="auth-demo-summary">
            <span class="auth-demo-summary-icon" aria-hidden="true">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                    <path d="M2 17l10 5 10-5"/>
                    <path d="M2 12l10 5 10-5"/>
                </svg>
            </span>
            <span class="auth-demo-summary-text">Try the demo account</span>
            <svg class="auth-demo-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="6 9 12 15 18 9"/>
            </svg>
        </summary>

        <div class="auth-demo-body">
            <p class="auth-demo-sub">
                Explore a finished debate and its adjudication report. A seeded
                account, so you can see the product with a record already on it.
            </p>

            <dl class="auth-demo-creds">
                <div class="auth-demo-cred">
                    <dt class="auth-demo-cred-label">Email</dt>
                    <dd class="auth-demo-cred-value">
                        <code>{{ $demoEmail }}</code>
                        <button type="button"
                                class="auth-demo-copy"
                                @click="copy(@js($demoEmail), 'email')"
                                :class="{ 'is-copied': copied === 'email', 'is-failed': copyFailed }"
                                :aria-label="copied === 'email' ? 'Demo email address copied' : 'Copy demo email address'">
                            <span x-show="copied !== 'email' && !copyFailed">Copy</span>
                            <span x-show="copied === 'email'" x-cloak>Copied</span>
                            <span x-show="copyFailed" x-cloak>Failed</span>
                        </button>
                    </dd>
                </div>

                <div class="auth-demo-cred">
                    <dt class="auth-demo-cred-label">Password</dt>
                    <dd class="auth-demo-cred-value">
                        <code>{{ $demoPassword }}</code>
                        <button type="button"
                                class="auth-demo-copy"
                                @click="copy(@js($demoPassword), 'password')"
                                :class="{ 'is-copied': copied === 'password', 'is-failed': copyFailed }"
                                :aria-label="copied === 'password' ? 'Demo password copied' : 'Copy demo password'">
                            <span x-show="copied !== 'password' && !copyFailed">Copy</span>
                            <span x-show="copied === 'password'" x-cloak>Copied</span>
                            <span x-show="copyFailed" x-cloak>Failed</span>
                        </button>
                    </dd>
                </div>
            </dl>

            @if ($showFillButton)
                {{-- One click puts both credentials in the form, so the person
                     only has to press Sign In. The confirmation is a live
                     region, so a screen reader announces it too. --}}
                <button type="button"
                        class="auth-demo-fill"
                        @click="fill(@js($demoEmail), @js($demoPassword))"
                        :class="{ 'is-filled': filled, 'is-failed': fillFailed }">
                    <svg x-show="!filled && !fillFailed" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    <svg x-show="filled" x-cloak width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <svg x-show="fillFailed" x-cloak width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <span x-show="!filled && !fillFailed">Fill in the form</span>
                    <span x-show="filled" x-cloak role="status" aria-live="polite">Credentials filled in</span>
                    <span x-show="fillFailed" x-cloak role="status" aria-live="polite">Could not fill the form</span>
                </button>
            @endif

            @if ($showLoginLink)
                <a href="{{ route('login') }}" class="auth-demo-cta">
                    Sign in with the demo account
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            @endif
        </div>
    </details>

    <script>
        /**
         * The demo account panel: copy a credential, or fill the form with
         * both of them.
         *
         * The Clipboard API is only available in a secure context, so it can
         * be missing or rejected. The button says so rather than silently
         * doing nothing, and the credential stays visible on screen anyway.
         */
        function demoAccount() {
            return {
                copied: null,
                copyFailed: false,
                filled: false,
                fillFailed: false,

                async copy(text, key) {
                    this.copied = null;
                    this.copyFailed = false;

                    try {
                        if (!navigator.clipboard || !navigator.clipboard.writeText) {
                            throw new Error('Clipboard API unavailable');
                        }
                        await navigator.clipboard.writeText(text);
                        this.copied = key;
                    } catch (e) {
                        this.copyFailed = true;
                    }

                    // Reset the label after a moment, unless the user is
                    // already copying the other credential.
                    setTimeout(() => {
                        if (this.copied === key) this.copied = null;
                        if (this.copyFailed) this.copyFailed = false;
                    }, 2000);
                },

                /**
                 * Put the demo credentials straight into the sign-in form.
                 *
                 * The native value setter is used, and an `input` event is
                 * dispatched, so Alpine and any listener see the change
                 * exactly as if the person had typed it.
                 */
                fill(email, password) {
                    const set = (id, value) => {
                        const field = document.getElementById(id);
                        if (!field) return false;

                        const setter = Object.getOwnPropertyDescriptor(
                            window.HTMLInputElement.prototype,
                            'value'
                        );
                        setter.set.call(field, value);
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        field.dispatchEvent(new Event('change', { bubbles: true }));

                        return true;
                    };

                    // This button is only rendered on the sign-in screen, so
                    // both fields are expected to be there. If the form ever
                    // changes shape, say so rather than doing nothing.
                    if (!set('email', email) || !set('password', password)) {
                        this.fillFailed = true;
                        setTimeout(() => { this.fillFailed = false; }, 2500);
                        return;
                    }

                    this.filled = true;
                    setTimeout(() => { this.filled = false; }, 2500);

                    // Put the cursor in the password field, so the next thing
                    // the person does is press Sign In.
                    const field = document.getElementById('password');
                    if (field) field.focus();
                },
            };
        }
    </script>
@endif
