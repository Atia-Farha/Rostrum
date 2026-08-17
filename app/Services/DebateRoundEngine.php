<?php

namespace App\Services;

use App\Models\Debate;
use App\Models\Round;

/**
 * Encapsulates Tournament Mode turn-order logic.
 *
 * Asian Parliamentary 1v1 adaptation (PRD §6.3, §13):
 * - Opening:  Government speaks first.
 * - Rebuttal: Same order as Opening (Government first).
 * - Closing:  REVERSED — whoever spoke second in Opening speaks first.
 *             (Prevents the same side from having the last word twice.)
 */
class DebateRoundEngine
{
    /**
     * Phase definitions in order (Tournament Mode).
     */
    public const PHASES = ['opening', 'rebuttal', 'closing'];

    /**
     * Seed the first round (Opening) for a newly created debate.
     */
    public function seedFirstRound(Debate $debate): Round
    {
        return Round::create([
            'debate_id'   => $debate->id,
            'phase'       => 'opening',
            'phase_order' => 1,
        ]);
    }

    /**
     * Seed a single implicit round for Sparring Mode (no fixed phases).
     */
    public function seedSparringRound(Debate $debate): Round
    {
        return Round::create([
            'debate_id'   => $debate->id,
            'phase'       => null,
            'phase_order' => 1,
        ]);
    }

    /**
     * Returns the side that speaks FIRST in a given phase.
     * This drives who the debate screen prompts to speak first.
     *
     * Opening/Rebuttal: Government always speaks first (WSDC/Asian Parliamentary
     * alternation — Government opens).
     * Closing: Reversed — Opposition first, so Government delivers the final word
     * (WSDC reply speeches: Opposition replies first, Proposition last).
     */
    public function firstSpeakerForPhase(Debate $debate, string $phase): string
    {
        if ($phase === 'closing') {
            // Reply-speech reversal: Opposition always replies first, so the
            // Government (which opened the round) delivers the final word.
            return 'opposition';
        }

        // Opening and Rebuttal: Government speaks first
        return 'government';
    }

    /**
     * Determine whether the user or the AI should speak next within the current round.
     * Returns 'user' or 'ai'.
     */
    public function nextSpeaker(Debate $debate, Round $round): string
    {
        $turnCount      = $round->turns()->count();
        $firstSpeaker   = $this->firstSpeakerForPhase($debate, $round->phase ?? 'opening');

        // Even turns (0-indexed): first speaker's turn. Odd turns: second speaker's turn.
        if ($turnCount % 2 === 0) {
            // First speaker's turn
            return $firstSpeaker === $debate->user_side ? 'user' : 'ai';
        }

        // Second speaker's turn
        return $firstSpeaker === $debate->user_side ? 'ai' : 'user';
    }

    /**
     * Check whether a given round (phase) is complete (both sides have spoken).
     * In Sparring Mode, the single round is open-ended and NEVER complete until manually ended.
     */
    public function isRoundComplete(Round $round): bool
    {
        if ($round->debate?->mode === 'sparring' || $round->phase === 'sparring') {
            return false;
        }

        return $round->turns()->count() >= 2;
    }

    /**
     * Advance to the next phase after the current round completes.
     * Returns the new Round, or null if all phases are complete.
     */
    public function advancePhase(Debate $debate): ?Round
    {
        $latestRound = $debate->rounds()->reorder('phase_order', 'desc')->first();
        if (! $latestRound) {
            return null;
        }

        $currentIndex = array_search($latestRound->phase, self::PHASES, true);
        $nextIndex    = $currentIndex + 1;

        if ($nextIndex >= count(self::PHASES)) {
            // All phases complete
            return null;
        }

        return Round::create([
            'debate_id'   => $debate->id,
            'phase'       => self::PHASES[$nextIndex],
            'phase_order' => $latestRound->phase_order + 1,
        ]);
    }

    /**
     * Check whether all three Tournament Mode phases are complete.
     */
    public function isDebateComplete(Debate $debate): bool
    {
        if ($debate->adjudication()->exists()) {
            return true;
        }

        if ($debate->mode === 'sparring') {
            return false;
        }

        $rounds = $debate->rounds()->get();
        if ($rounds->count() < 3) {
            return false;
        }

        foreach ($rounds as $round) {
            if (! $this->isRoundComplete($round)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the current active round (the last incomplete one).
     */
    public function currentRound(Debate $debate): ?Round
    {
        return $debate->rounds()
            ->orderBy('phase_order')
            ->get()
            ->first(fn (Round $round) => ! $this->isRoundComplete($round));
    }

    /**
     * Build the full state needed for the debate Blade view.
     */
    public function buildViewState(Debate $debate): array
    {
        $debate->load(['motion', 'persona', 'rounds.turns.rewrite']);

        $currentRound  = $this->currentRound($debate);
        $isComplete    = $this->isDebateComplete($debate);
        $nextSpeaker   = $currentRound ? $this->nextSpeaker($debate, $currentRound) : null;

        $phaseDurations = config('debate.phase_duration', [
            'opening'  => 180,
            'rebuttal' => 120,
            'closing'  => 120,
        ]);

        return [
            'debate'        => $debate,
            'currentRound'  => $currentRound,
            'nextSpeaker'   => $nextSpeaker,
            'isComplete'    => $isComplete,
            'currentPhase'  => $debate->mode === 'sparring' ? 'sparring' : ($currentRound?->phase ?? 'opening'),
            'phaseDuration' => $currentRound
                ? ($phaseDurations[$currentRound->phase] ?? 120)
                : 0,
            'rounds'        => $debate->rounds,
            'phases'        => self::PHASES,
        ];
    }
}
