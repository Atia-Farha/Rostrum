<?php

use App\Http\Controllers\DebateController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Health check — verifies app + database are alive (used by monitors/tests)
Route::get('/healthz', function () {
    try {
        DB::select('select 1');
        return response()->json(['status' => 'ok'], 200);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error'], 500);
    }
});

// Home / Language select
Route::get('/', [HomeController::class, 'index'])->name('home');

// Setup screen
Route::get('/setup', [SetupController::class, 'index'])->name('setup');
Route::post('/motions/generate', [SetupController::class, 'generateMotion'])->name('motions.generate');
Route::post('/debates', [SetupController::class, 'createDebate'])->name('debates.create');

// Debate round
Route::get('/debates/{debate}', [DebateController::class, 'show'])->name('debates.show');
Route::post('/debates/{debate}/turns', [DebateController::class, 'submitTurn'])->name('debates.turns.submit');
Route::post('/debates/{debate}/turns/{turn}/rewrite', [DebateController::class, 'rewriteTurn'])->name('debates.turns.rewrite');
Route::post('/debates/{debate}/adjudicate', [DebateController::class, 'adjudicate'])->name('debates.adjudicate');

// Feedback & transcript
Route::get('/debates/{debate}/feedback', [DebateController::class, 'feedback'])->name('debates.feedback');
Route::get('/debates/{debate}/transcript', [DebateController::class, 'transcript'])->name('debates.transcript');

// Debate History
Route::get('/history', [HistoryController::class, 'index'])->name('history.index');
Route::delete('/debates/{debate}', [HistoryController::class, 'destroy'])->name('debates.destroy');
