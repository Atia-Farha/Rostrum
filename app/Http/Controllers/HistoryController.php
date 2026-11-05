<?php

namespace App\Http\Controllers;

use App\Models\Debate;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    /**
     * GET /dashboard/history — View the signed-in user's past debates.
     *
     * The list contains every debate the account owns, and nothing else.
     *
     * The seeded demo debate belongs to the demo account alone. It is
     * deliberately not shared: an account's history is a record of that
     * account's own practice, and putting somebody else's debate in it
     * would make the record a lie. A newly registered account therefore
     * opens on an empty ledger.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $debates = Debate::with(['motion', 'persona', 'adjudication'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Default tab = the mode the user has records for, so the history
        // page never opens on an empty tab. With no records at all there
        // is nothing to prefer, and the page opens on Tournament — the
        // primary format, and the first tab on the page.
        $defaultTab = $debates->isEmpty() || $debates->contains(fn ($d) => $d->mode === 'tournament')
            ? 'tournament'
            : 'sparring';

        $tournamentCount = $debates->where('mode', 'tournament')->count();
        $sparringCount   = $debates->where('mode', 'sparring')->count();

        // The delete button is only shown on debates the viewer actually owns,
        // so nobody is offered an action that would be rejected. Every row
        // in this list is the viewer's own, so every row is deletable —
        // the check is kept because the list and the rule must not drift.
        $deletableIds = $debates
            ->filter(fn (Debate $debate) => $debate->user_id === $userId)
            ->pluck('id')
            ->all();

        return view('history', compact(
            'debates', 'defaultTab', 'tournamentCount', 'sparringCount', 'deletableIds'
        ));
    }

    /**
     * DELETE /debates/{debate} — Delete a debate.
     * Child records (rounds, turns, rewrites, adjudication) are removed
     * automatically via ON DELETE CASCADE.
     *
     * Authorization is enforced by the `can:delete,debate` route middleware,
     * which resolves to App\Policies\DebatePolicy.
     */
    public function destroy(Request $request, Debate $debate)
    {
        $debate->delete();

        return redirect()->route('history.index')->with('success', 'Debate session deleted.');
    }
}
