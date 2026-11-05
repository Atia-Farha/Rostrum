<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebateController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| Only three pages are reachable without an account: the landing page and
| the two credential screens. Everything else lives behind the `auth`
| middleware and redirects anonymous visitors to the sign-in form.
|
*/

// Health check — verifies app + database are alive (used by monitors/tests).
// Deliberately public and deliberately reveals nothing beyond "ok".
Route::get('/healthz', function () {
    try {
        DB::select('select 1');

        return response()->json(['status' => 'ok'], 200);
    } catch (Throwable $e) {
        return response()->json(['status' => 'error'], 500);
    }
});

// Landing page
Route::get('/', [HomeController::class, 'index'])->name('home');

// Registration / sign-in are open to guests; already-signed-in users are
// bounced to their account by the `guest` middleware.
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('register.store');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.store');
});

// Sign-out is available to signed-in users only.
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Protected application routes
|--------------------------------------------------------------------------
|
| Every dashboard page and every endpoint that mutates data requires an
| authenticated account. The `auth` middleware sends anonymous visitors to
| the sign-in screen and remembers where they were headed.
|
*/

Route::middleware('auth')->group(function () {

    /*
    |----------------------------------------------------------------------
    | Dashboard
    |----------------------------------------------------------------------
    |
    | Every signed-in page lives under the /dashboard prefix, so the whole
    | application surface is namespaced in one place:
    |
    |   /dashboard/overview   the dashboard landing page
    |   /dashboard/history    past debate sessions
    |   /dashboard/setup      start a new debate
    |   /dashboard/settings   account settings
    |
    | The route *names* are deliberately unchanged, so every route() call
    | in the views and controllers keeps working — only the URLs move.
    |
    */

    // ── Overview (the dashboard landing page) ─────────────────────────────
    Route::get('/dashboard/overview', [DashboardController::class, 'index'])->name('dashboard');

    // ── Account settings ──────────────────────────────────────────────────
    Route::get('/dashboard/settings', [AccountController::class, 'index'])->name('account.index');
    Route::patch('/dashboard/settings', [AccountController::class, 'update'])->name('account.update');
    Route::patch('/dashboard/settings/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
    Route::delete('/dashboard/settings', [AccountController::class, 'destroy'])->name('account.destroy');

    // ── Setup / start a new debate ─────────────────────────────────────────
    Route::get('/dashboard/setup', [SetupController::class, 'index'])->name('setup');
    Route::post('/motions/generate', [SetupController::class, 'generateMotion'])
        ->middleware('throttle:api')
        ->name('motions.generate');
    Route::post('/debates', [SetupController::class, 'createDebate'])->name('debates.create');

    // ── Debate arena ───────────────────────────────────────────────────────
    Route::get('/debates/{debate}', [DebateController::class, 'show'])
        ->middleware('can:view,debate')
        ->name('debates.show');
    Route::post('/debates/{debate}/turns', [DebateController::class, 'submitTurn'])
        ->middleware(['can:participate,debate', 'throttle:api'])
        ->name('debates.turns.submit');
    Route::post('/debates/{debate}/turns/{turn}/rewrite', [DebateController::class, 'rewriteTurn'])
        ->middleware(['can:rewrite,debate', 'throttle:api'])
        ->name('debates.turns.rewrite');
    Route::post('/debates/{debate}/adjudicate', [DebateController::class, 'adjudicate'])
        ->middleware(['can:adjudicate,debate', 'throttle:api'])
        ->name('debates.adjudicate');

    // ── Report & transcript ────────────────────────────────────────────────
    Route::get('/debates/{debate}/feedback', [DebateController::class, 'feedback'])
        ->middleware('can:view,debate')
        ->name('debates.feedback');
    Route::get('/debates/{debate}/transcript', [DebateController::class, 'transcript'])
        ->middleware('can:view,debate')
        ->name('debates.transcript');

    // User voice recordings are private — served by the controller (which
    // checks ownership) rather than straight off the public disk.
    Route::get('/debates/{debate}/audio/{file}', [DebateController::class, 'audio'])
        ->middleware('can:view,debate')
        ->name('debates.audio');

    // ── Debate history ─────────────────────────────────────────────────────
    Route::get('/dashboard/history', [HistoryController::class, 'index'])->name('history.index');
    Route::delete('/debates/{debate}', [HistoryController::class, 'destroy'])
        ->middleware('can:delete,debate')
        ->name('debates.destroy');
});
