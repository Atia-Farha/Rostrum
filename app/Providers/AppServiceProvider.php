<?php

namespace App\Providers;

use App\Models\Debate;
use App\Policies\DebatePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerRateLimiters();
    }

    /**
     * Debate ownership is enforced centrally, so every route that touches a
     * debate checks the same rule. See App\Policies\DebatePolicy.
     *
     * Laravel resolves a policy method before a string gate of the same name,
     * so `can:view,debate`, `can:participate,debate`, `can:adjudicate,debate`
     * and `can:rewrite,debate` all resolve here.
     */
    private function registerPolicies(): void
    {
        Gate::policy(Debate::class, DebatePolicy::class);
    }

    /**
     * Rate limiters.
     *
     * The Gemini and ElevenLabs endpoints cost money per call, so they are
     * capped per account rather than per IP — one abusive account must not be
     * able to exhaust the shared quota.
     */
    private function registerRateLimiters(): void
    {
        // Sign-in: the per-account lockout in AuthController (5 failures) is
        // the primary protection for a single target, so the route-level
        // limits here are backstops for the *spray* pattern — one attempt
        // spread across many addresses from one host.
        //
        // The per-address bucket is keyed by email AND ip so one attacker
        // cannot lock every account out by hammering a single address.
        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(12)->by('login:'.sha1($email).'|'.$request->ip()),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        // Registration: capped per IP so accounts cannot be mass-created.
        RateLimiter::for('register', function (Request $request) {
            return [
                Limit::perMinute(5)->by('register:'.$request->ip()),
                Limit::perHour(20)->by('register-hour:'.$request->ip()),
            ];
        });

        // AI-backed endpoints: per account, with a broader per-IP ceiling for
        // the case where an attacker rotates accounts from one host.
        RateLimiter::for('api', function (Request $request) {
            $userId = $request->user()?->id;

            return [
                Limit::perMinute(20)->by('api-user:'.($userId ?? 'guest').'|'.$request->ip()),
                Limit::perMinute(60)->by('api-ip:'.$request->ip()),
                Limit::perHour(300)->by('api-hour:'.($userId ?? 'guest').'|'.$request->ip()),
            ];
        });
    }
}
