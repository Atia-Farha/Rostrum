<?php

namespace App\Http\Controllers;

use App\Models\Debate;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * The rubric's three criteria and the marks each one carries.
     * The adjudication prompt scores Matter out of 40 and Manner and
     * Method out of 30, so every average has to be read against its
     * own maximum rather than against 100.
     */
    private const CRITERIA = [
        ['key' => 'matter', 'label' => 'Matter', 'max' => 40],
        ['key' => 'manner', 'label' => 'Manner', 'max' => 30],
        ['key' => 'method', 'label' => 'Method', 'max' => 30],
    ];

    /**
     * The difficulties a debate can be set at, easiest first.
     */
    private const DIFFICULTIES = [
        'beginner'       => 'Beginner',
        'intermediate'   => 'Intermediate',
        'advanced'       => 'Advanced',
        'world_champion' => 'World Champion',
    ];

    /**
     * GET /dashboard/overview — The overview page.
     *
     * This is where a signed-in user lands. It answers three questions,
     * in order: how much have I done, how have I been doing, and what
     * did I do last.
     *
     * Every query is scoped to the signed-in account.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // The user's own debates, newest first. The seeded demo debate
        // is not part of this collection: it belongs to the demo account,
        // so counting it would put someone else's practice in this
        // account's statistics.
        $debates = $user->debates()
            ->with(['motion', 'persona', 'adjudication'])
            ->orderBy('created_at', 'desc')
            ->get();

        $adjudicated = $debates->filter(fn (Debate $debate) => $debate->adjudication !== null);

        $stats = [
            'total' => $debates->count(),
            'tournament' => $debates->where('mode', 'tournament')->count(),
            'sparring' => $debates->where('mode', 'sparring')->count(),
            'adjudicated' => $adjudicated->count(),

            // The format split of the *reports*, kept apart from the
            // format split of the debates. A note sitting under the
            // reports count has to describe the same set the count
            // names — the two splits are different numbers, because a
            // debate in progress is counted in one and not the other.
            'tournament_reports' => $adjudicated->where('mode', 'tournament')->count(),
            'sparring_reports' => $adjudicated->where('mode', 'sparring')->count(),

            'in_progress' => $debates->count() - $adjudicated->count(),
            'avg_score' => $adjudicated->isEmpty()
                ? null
                : (int) round($adjudicated->avg('adjudication.total_score')),
            'best_score' => $adjudicated->isEmpty()
                ? null
                : (int) $adjudicated->max('adjudication.total_score'),
        ];

        // The rubric average, so the dashboard can show *where* the points
        // come from rather than a bare total.
        $breakdown = $adjudicated->isEmpty() ? null : [
            'matter' => (int) round($adjudicated->avg('adjudication.matter_score')),
            'manner' => (int) round($adjudicated->avg('adjudication.manner_score')),
            'method' => (int) round($adjudicated->avg('adjudication.method_score')),
        ];

        // The score trend. Oldest first, so the line reads left to right
        // as time passing. Only scored debates carry a score, so an
        // unfinished debate is a gap rather than a zero — plotting it as
        // zero would draw a collapse in performance that never happened.
        $trend = $adjudicated
            ->sortBy('created_at')
            ->map(fn (Debate $debate) => [
                'id'    => $debate->id,
                'score' => (int) $debate->adjudication->total_score,
                'label' => $debate->created_at->format('d M'),
            ])
            ->values()
            ->all();

        // How the reports split by difficulty. Every difficulty is listed
        // even when it has no reports yet: a bar chart whose axis changes
        // shape between visits is harder to read, not easier.
        $byDifficulty = collect(self::DIFFICULTIES)
            ->map(fn (string $label, string $key) => [
                'key'   => $key,
                'label' => $label,
                'count' => $adjudicated->where('difficulty', $key)->count(),
            ])
            ->values()
            ->all();

        // How the reports split by format.
        $byFormat = [
            ['key' => 'tournament', 'label' => 'Tournament', 'count' => $stats['tournament_reports']],
            ['key' => 'sparring',   'label' => 'Sparring',   'count' => $stats['sparring_reports']],
        ];

        // The debate the user was last in, so "Continue" can point at it.
        $resumable = $debates->first(fn (Debate $debate) => $debate->status !== 'adjudicated');

        // The four most recent debates. Four is enough to show a shape
        // without turning the page into a second copy of the history
        // page, which lists every one of them.
        $recentDebates = $debates->take(4)->values();

        return view('dashboard', [
            'user'          => $user,
            'stats'         => $stats,
            'breakdown'     => $breakdown,
            'trend'         => $trend,
            'byDifficulty'  => $byDifficulty,
            'byFormat'      => $byFormat,
            'recentDebates' => $recentDebates,
            'resumable'     => $resumable,
            'criteria'      => self::CRITERIA,
        ]);
    }
}
