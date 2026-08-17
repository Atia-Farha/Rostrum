<?php

namespace App\Http\Controllers;

use App\Models\Debate;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    /**
     * GET /history — View the user's past debates (plus the seeded demo debate).
     */
    public function index(Request $request)
    {
        $debates = Debate::with(['motion', 'persona', 'adjudication'])
            ->where(function ($query) use ($request) {
                $query->where('session_id', $request->session()->getId())
                      ->orWhere('session_id', config('debate.seed_session_id'));
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Default tab = the mode the user has records for, so the history
        // page never opens on an empty tab.
        $defaultTab = $debates->contains(fn ($d) => $d->mode === 'tournament')
            ? 'tournament'
            : 'sparring';

        $tournamentCount = $debates->where('mode', 'tournament')->count();
        $sparringCount   = $debates->where('mode', 'sparring')->count();

        return view('history', compact('debates', 'defaultTab', 'tournamentCount', 'sparringCount'));
    }

    /**
     * DELETE /debates/{debate} — Delete a debate.
     * Child records (rounds, turns, rewrites, adjudication) are removed
     * automatically via ON DELETE CASCADE.
     */
    public function destroy(Debate $debate)
    {
        $debate->delete();

        return redirect()->route('history.index')->with('success', 'Debate session deleted.');
    }
}
