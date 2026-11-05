<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render serves the app over HTTPS behind its own edge proxy.
        // Trust forwarded headers so asset/link URLs are generated with https://
        // instead of http:// (which browsers block as mixed content).
        $middleware->trustProxies(at: '*');
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_AWS_ELB);

        // Unauthenticated visitors are sent to the sign-in screen, and
        // already-signed-in users away from register/login.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        // Security headers on every response. These are cheap, dependency-free
        // protections against the usual browser-side attack classes.
        //
        // Note: CSRF protection (PreventRequestForgery) is already part of
        // Laravel's default `web` middleware group, so it is not re-registered
        // here.
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // JSON is used for anything under /api/* and for any request that
        // explicitly asks for it (Accept: application/json) or is an XHR.
        // Everything else — the ordinary Blade form posts — keeps the
        // default redirect-back-with-errors behaviour.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
