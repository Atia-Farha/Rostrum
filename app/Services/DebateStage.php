<?php

namespace App\Services;

use App\Models\Debate;
use App\Models\Round;
use App\Models\Turn;

/**
 * Works out where a speaker stands in the round at the moment they speak.
 *
 * The prompt used to be built from one thing — the phase name. That is
 * not enough, because a phase is a container for two speeches, and the
 * two speeches are not the same speech:
 *
 *   - In the Opening phase, the first speaker builds the case and the
 *     second speaker answers it. Telling the second speaker it is in
 *     the "opening" phase is true and useless — it is the reason the
 *     second opening speech so often comes out as a second opening
 *     speech.
 *   - In the Closing phase, the first speaker weighs the debate and
 *     the second speaker takes the last word.
 *
 * So this service answers four questions, and the prompt is built from
 * the answers:
 *
 *   1. Which phase is this, what is the phase FOR in the round, and
 *      which slot in it does this speaker hold — first or second?
 *   2. Which speech are they answering, and what did it actually say?
 *   3. What is their own position — their last speech, and what their
 *      side has already established?
 *   4. What is left of the round after this speech?
 *
 * The answers come from the debate's own turns rather than from a guess
 * about how many speeches have happened, so a round that was resumed,
 * retried, or partly failed still produces a prompt that describes the
 * round as it actually is.
 */
class DebateStage
{
    /**
     * The slot a speaker occupies within a phase: 'first' or 'second'.
     */
    public const FIRST = 'first';

    public const SECOND = 'second';

    /**
     * How to name each speech in the round.
     *
     * The name describes the SPEECH the opponent gave, not the phase
     * the current speaker is standing in. The first speaker of the
     * rebuttal phase is answering an opening speech; if the prompt
     * called it "the opponent's rebuttal", the model would be told to
     * answer something the opponent had not said yet — and would
     * answer it by making something up.
     */
    private const SPEECH_NAMES = [
        'opening.first' => 'opening speech',
        'opening.second' => 'reply speech',
        'rebuttal.first' => 'rebuttal speech',
        'rebuttal.second' => 'final rebuttal',
        'closing.first' => 'closing speech',
        'closing.second' => 'final reply',
    ];

    public function __construct(
        private DebateRoundEngine $engine,
    ) {}

    /**
     * Build the full stage brief for a speaker about to speak.
     *
     * @param  Debate  $debate  The debate being argued.
     * @param  Round  $round  The round the speech belongs to.
     * @param  string  $speaker  'user' or 'ai'.
     * @param  string  $side  The side this speaker is arguing.
     * @return array<string, mixed>
     */
    public function brief(Debate $debate, Round $round, string $speaker, string $side): array
    {
        $phase = $round->phase ?? 'sparring';
        $slot = $this->slotFor($debate, $round, $speaker);
        $key = $phase === 'sparring' ? 'sparring' : "{$phase}.{$slot}";

        $profile = config('debate.stage_profiles', [])[$key]
            ?? config('debate.stage_profiles', [])['sparring']
            ?? [];

        $labels = config('debate.side_labels.'.$side, []);
        $opponent = $debate->user_side === $side ? $debate->aiSide() : $debate->user_side;
        $oppoLabels = config('debate.side_labels.'.$opponent, []);

        [$answering, $answeringLabel] = $this->resolveOpponentSpeech($debate, $round, $speaker);
        [$ownLast, $ownLastLabel] = $this->ownLastSpeech($debate, $round, $speaker);

        return [
            'phase' => $phase,
            'phase_purpose' => config("debate.phase_purposes.{$phase}", 'a phase of a debate round'),
            'slot' => $slot,
            'slot_key' => $key,
            'role' => $profile['role'] ?? 'Speech',
            'job' => $profile['job'] ?? '',
            'constraints' => $profile['constraints'] ?? [],
            'word_count' => $this->wordCount($phase, $slot),
            'side' => $side,
            'side_role' => $labels['role'] ?? $side,
            'side_first' => $labels['first'] ?? $side,
            'opponent' => $oppoLabels['short'] ?? $opponent,
            'opponent_first' => $oppoLabels['first'] ?? 'the opponent',
            'answering' => $answering,
            'answering_label' => $answeringLabel,
            'own_last' => $ownLast,
            'own_last_label' => $ownLastLabel,
            'own_summary' => $this->ownCaseSummary($debate, $round, $speaker),
            'phases_done' => $this->phasesDone($debate, $round),
            'phases_left' => $this->phasesLeft($debate, $round),
        ];
    }

    /**
     * Which slot in the phase this speaker occupies.
     *
     * This is derived from the round's own turns rather than from a
     * counter, so it stays correct when a round was entered halfway,
     * when a speech failed to save, or when the AI spoke first.
     */
    public function slotFor(Debate $debate, Round $round, string $speaker): string
    {
        $spoken = $round->turns()->pluck('speaker')->unique();

        if ($spoken->isEmpty()) {
            // Nothing has been said in this round, so whoever the round
            // engine says opens it takes the first slot.
            return $this->engine->nextSpeaker($debate, $round) === $speaker
                ? self::FIRST
                : self::SECOND;
        }

        // Something has been said. If it was the other side, this is the
        // second slot of the phase. If it was this speaker, then their
        // speech failed to save and they are being asked again — still
        // the second slot, so they answer something rather than build
        // the same case twice.
        return self::SECOND;
    }

    /**
     * The transcript of the speech this speaker is answering, and a
     * label for it.
     *
     * The opponent's speech in the CURRENT phase is preferred. If the
     * opponent has not spoken in this phase — which is the case for
     * the first speaker of every phase after the opening — the most
     * recent speech they gave in an earlier phase is used instead,
     * because that is the speech this one answers.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function resolveOpponentSpeech(
        Debate $debate,
        Round $round,
        string $speaker,
    ): array {
        $opponentType = $speaker === 'user' ? 'ai' : 'user';

        $inRound = $round->turns()
            ->where('speaker', $opponentType)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($inRound) {
            return [
                $inRound->transcript,
                $this->speechLabel($debate, $round, $opponentType),
            ];
        }

        // The opponent has not spoken in this phase. Look back through
        // the phases already argued for their most recent speech.
        $earlier = $debate->rounds()
            ->where('phase_order', '<', $round->phase_order)
            ->orderByDesc('phase_order')
            ->get()
            ->flatMap(fn (Round $r) => $r->turns()->where('speaker', $opponentType)->get())
            ->sortByDesc(fn (Turn $t) => $t->created_at)
            ->first();

        if (! $earlier) {
            // Nothing to answer — the opening phase's first speaker.
            return [null, null];
        }

        return [
            $earlier->transcript,
            $this->speechLabel($debate, $earlier->round, $opponentType),
        ];
    }

    /**
     * How to refer to a speech in the round.
     */
    public function speechLabel(Debate $debate, Round $round, string $speakerType): string
    {
        $phase = $round->phase ?? 'sparring';

        if ($phase === 'sparring') {
            return 'the opponent\'s last reply';
        }

        $key = $phase.'.'.$this->slotOf($debate, $round, $speakerType);

        $name = self::SPEECH_NAMES[$key] ?? 'most recent speech';

        return 'the opponent\'s '.$name;
    }

    /**
     * The slot the given speaker type occupied in this round.
     */
    public function slotOf(Debate $debate, Round $round, string $speakerType): string
    {
        $firstSpeaker = $this->engine->firstSpeakerForPhase($debate, $round->phase ?? 'opening');
        $firstType = $firstSpeaker === $debate->user_side ? 'user' : 'ai';

        return $firstType === $speakerType ? self::FIRST : self::SECOND;
    }

    /**
     * The speaker's own most recent speech, and a label for it.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function ownLastSpeech(Debate $debate, Round $round, string $speaker): array
    {
        $inRound = $round->turns()
            ->where('speaker', $speaker)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (! $inRound) {
            // Not in this round — look at the last round they spoke in.
            $earlier = $debate->rounds()
                ->where('phase_order', '<', $round->phase_order)
                ->orderByDesc('phase_order')
                ->get()
                ->flatMap(fn (Round $r) => $r->turns()->where('speaker', $speaker)->get())
                ->sortByDesc(fn (Turn $t) => $t->created_at)
                ->first();

            if (! $earlier) {
                return [null, null];
            }

            return [$earlier->transcript, 'your previous speech, given in the '
                .($earlier->round?->phase ?? 'previous').' phase'];
        }

        $phase = $round->phase ?? 'sparring';
        $slot = $this->slotOf($debate, $round, $speaker);

        $label = match ($phase) {
            'sparring' => 'your previous reply',
            'opening' => $slot === self::FIRST ? 'your opening speech' : 'your reply',
            'rebuttal' => $slot === self::FIRST ? 'your rebuttal speech' : 'your final rebuttal',
            'closing' => $slot === self::FIRST ? 'your closing speech' : 'your final reply',
            default => 'your previous speech',
        };

        return [$inRound->transcript, $label];
    }

    /**
     * A short account of what this speaker's side has already put into
     * the round.
     *
     * The verbatim speeches are already in the transcript the model is
     * given, so repeating them here would only make the prompt longer.
     * What the model does not have is the shape: how many speeches
     * this side has made and in which phases. That is what stops a
     * reply speech from rebuilding the case it has already made.
     */
    private function ownCaseSummary(Debate $debate, Round $round, string $speaker): ?string
    {
        $own = $debate->rounds()
            ->where('phase_order', '<=', $round->phase_order)
            ->orderBy('phase_order')
            ->get()
            ->flatMap(fn (Round $r) => $r->turns()->where('speaker', $speaker)->get())
            ->filter(fn (Turn $t) => trim((string) $t->transcript) !== '');

        if ($own->isEmpty()) {
            return null;
        }

        $byPhase = $own
            ->groupBy(fn (Turn $t) => $t->round?->phase ?? 'sparring')
            ->map(fn ($turns) => $turns->count());

        $parts = [];
        foreach ($byPhase as $phase => $count) {
            $parts[] = $count > 1
                ? "{$count} speeches in the {$phase} phase"
                : "1 speech in the {$phase} phase";
        }

        return 'You have already given '.implode(', and ', $parts)
            .'. Your earlier speeches are in the transcript below — build on them rather than restating them.';
    }

    /**
     * The phases already argued, in order.
     *
     * @return string[]
     */
    private function phasesDone(Debate $debate, Round $round): array
    {
        return $debate->rounds()
            ->where('phase_order', '<', $round->phase_order)
            ->orderBy('phase_order')
            ->pluck('phase')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The phases still to come after this one.
     *
     * @return string[]
     */
    private function phasesLeft(Debate $debate, Round $round): array
    {
        $done = $this->phasesDone($debate, $round);
        $now = $round->phase;

        return collect(DebateRoundEngine::PHASES)
            ->reject(fn (string $p) => $p === $now || in_array($p, $done, true))
            ->values()
            ->all();
    }

    /**
     * The word budget for this phase and slot.
     *
     * The slot keys contain a dot, so the array is read directly rather
     * than through Laravel's dot-notation lookup — "opening.first"
     * would otherwise be read as the "first" key inside "opening".
     */
    public function wordCount(string $phase, string $slot): string
    {
        $counts = config('debate.ai_word_count', []);

        return $counts["{$phase}.{$slot}"]
            ?? $counts[$phase]
            ?? '100–160';
    }
}
