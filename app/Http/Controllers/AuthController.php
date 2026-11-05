<?php

namespace App\Http\Controllers;

use App\Models\Debate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * How many failed sign-in attempts lock an account for a while.
     */
    private const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * How long (seconds) an account stays locked after too many failures.
     */
    private const LOCKOUT_SECONDS = 900;

    /**
     * GET /register — render the registration screen.
     */
    public function showRegister()
    {
        // The credential screens stand on their own: no site nav, full height.
        View::share('hideNav', true);

        return view('auth.register');
    }

    /**
     * POST /register — Create an account and sign the user in.
     *
     * Debates can only be created by a signed-in account, so a brand-new
     * user starts with an empty history.
     */
    public function register(Request $request): RedirectResponse
    {
        // Normalize BEFORE validating, so the uniqueness rule checks the same
        // string that will actually be stored. Otherwise "Ada@Example.com" and
        // "ada@example.com" would both pass the check and collide on write.
        $request->merge(['email' => $this->normalizeEmail((string) $request->input('email'))]);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|string|email:rfc|max:255|unique:users,email',
            'password' => ['required', 'confirmed', $this->passwordRule()],
        ]);

        // Capture the guest session BEFORE authenticating — Auth::login()
        // regenerates the session ID, which would orphan the guest debates.
        $guestSessionId = $request->session()->getId();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        Auth::login($user, remember: $request->boolean('remember'));

        // Debates created before accounts existed have no owner. The seeded
        // account adopts them, so nothing becomes unreachable under the
        // ownership model — see SeedAccountSeeder.
        $this->claimSessionDebates($user, $guestSessionId);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Welcome to Rostrum — your account is ready.');
    }

    /**
     * GET /login — render the sign-in screen.
     */
    public function showLogin()
    {
        // The credential screens stand on their own: no site nav, full height.
        View::share('hideNav', true);

        return view('auth.login');
    }

    /**
     * POST /login — Authenticate an existing account.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|string|email:rfc',
            'password' => 'required|string',
        ]);

        $email = $this->normalizeEmail($credentials['email']);

        // ── Brute-force lockout ─────────────────────────────────────────────
        // Counted per account, so one attacker cannot lock every account out
        // by hammering a single address. The route-level throttle is a
        // backstop against a spray across many addresses; this counter is
        // what protects a single account.
        if ($this->isLockedOut($email)) {
            throw ValidationException::withMessages([
                'email' => 'Too many failed sign-in attempts. Please try again in a few minutes.',
            ]);
        }

        // Capture the guest session BEFORE authenticating — Auth::attempt()
        // regenerates the session ID on success.
        $guestSessionId = $request->session()->getId();

        if (! Auth::attempt(['email' => $email, 'password' => $credentials['password']], $request->boolean('remember'))) {
            $this->recordFailedAttempt($email);

            // On the attempt that trips the limit, tell the user plainly
            // instead of returning a generic "no match" message.
            if ($this->isLockedOut($email)) {
                throw ValidationException::withMessages([
                    'email' => 'Too many failed sign-in attempts. This account is locked for a few minutes.',
                ]);
            }

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        // A successful sign-in clears the failure counter.
        $this->clearFailedAttempts($email);

        // Regenerate the session ID to prevent session fixation.
        $request->session()->regenerate();

        $this->claimSessionDebates(Auth::user(), $guestSessionId);

        return redirect()
            ->intended(route('dashboard'))
            ->with('success', 'Signed in successfully.');
    }

    /**
     * POST /logout — Sign the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('home')
            ->with('success', 'You have been signed out.');
    }

    /**
     * Adopt any debates left without an owner.
     *
     * Debates can only be created by a signed-in account, so in practice
     * there are none — except rows that predate the account feature. The
     * seeded account picks those up; this is the safety net for a database
     * that was populated before the seeder ran.
     *
     * Only unowned debates are adopted, so signing in never steals another
     * account's debates that happen to share a session id.
     */
    private function claimSessionDebates(User $user, string $guestSessionId): void
    {
        if ($guestSessionId === '') {
            return;
        }

        Debate::where('session_id', $guestSessionId)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);
    }

    /**
     * The password policy applied to registration and password changes.
     */
    private function passwordRule(): Password
    {
        return Password::min(10)
            ->letters()
            ->mixedCase()
            ->numbers();
    }

    /**
     * Normalize an email address for storage and lookup.
     */
    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * The cache key holding the failed sign-in counter for an account.
     */
    private function failureKey(string $email): string
    {
        return 'login-failures:'.sha1(mb_strtolower($email));
    }

    /**
     * Has this account exceeded the allowed number of failed sign-ins?
     */
    private function isLockedOut(string $email): bool
    {
        return (int) Cache::get($this->failureKey($email), 0) >= self::MAX_LOGIN_ATTEMPTS;
    }

    /**
     * Record a failed sign-in, locking the account once the limit is passed.
     */
    private function recordFailedAttempt(string $email): void
    {
        $key = $this->failureKey($email);

        $attempts = (int) Cache::get($key, 0) + 1;

        Cache::put($key, $attempts, self::LOCKOUT_SECONDS);
    }

    /**
     * Clear the failed sign-in counter after a successful sign-in.
     */
    private function clearFailedAttempts(string $email): void
    {
        Cache::forget($this->failureKey($email));
    }
}
