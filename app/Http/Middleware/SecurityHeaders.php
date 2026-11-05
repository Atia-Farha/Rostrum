<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline security headers to every response.
 *
 * These are deliberately conservative so they cannot break the app:
 *   - X-Frame-Options / frame-ancestors  → clickjacking protection
 *   - X-Content-Type-Options               → stops MIME sniffing
 *   - Referrer-Policy                     → limits referrer leakage
 *   - Permissions-Policy                  → disables unused browser APIs
 *   - Cross-Origin-Opener-Policy          → isolates the browsing context
 *
 * A Content-Security-Policy is NOT set here: the app loads Google Fonts and
 * uses inline Alpine.js expressions, so a strict CSP would need nonces
 * threaded through every view. That is a larger change and is left out of
 * this pass deliberately.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(self), microphone=(self), geolocation=(), interest-cohort=()',
        ];

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
