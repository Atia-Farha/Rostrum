<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DebateMode;
use App\Enums\DebateStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

final class Debate extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'session_id',
        'user_id',
        'motion_id',
        'user_side',
        'persona_id',
        'difficulty',
        'mode',
        'language',
        'status',
        'ai_provider',
        'ai_error',
    ];

    protected $casts = [
        'status' => DebateStatus::class,
        'mode' => DebateMode::class,
    ];

    public function motion(): BelongsTo
    {
        return $this->belongsTo(Motion::class);
    }

    /**
     * The account that owns this debate. NULL for guest debates that have
     * not been claimed by a signed-in user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class)->orderBy('phase_order');
    }

    public function adjudication(): HasOne
    {
        return $this->hasOne(Adjudication::class);
    }

    /**
     * Returns the AI's side (opposite of user's side).
     */
    public function aiSide(): string
    {
        return $this->user_side === 'government' ? 'opposition' : 'government';
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Only debates that belong to a specific user.
     */
    public function scopeForUser(Builder $query, int|string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Only debates that have been adjudicated (have a report).
     */
    public function scopeAdjudicated(Builder $query): Builder
    {
        return $query->whereHas('adjudication');
    }

    /**
     * Only debates still in progress (no adjudication yet).
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereDoesntHave('adjudication');
    }

    // ── Accessors / Methods ───────────────────────────────────────────────────

    /**
     * Returns all turns across all rounds, ordered chronologically.
     *
     * When rounds are already eager-loaded (via `load('rounds.turns')`),
     * this method leverages the in-memory collection to avoid extra queries.
     * Otherwise it falls back to a single indexed query.
     *
     * The ordering is by round phase_order, then by created_at, then by id.
     * Turns in one round are often written within the same second — the user
     * speaks and the AI answers immediately — and ordering only by created_at
     * leaves the database free to return those two in either order. A
     * transcript that puts the AI's answer before the speech it was answering
     * is worse than useless: it teaches the model that the opponent replies
     * to things nobody has said yet.
     *
     * @return Collection<int, Turn>
     */
    public function allTurns(): Collection
    {
        // If rounds are already loaded (the common case after buildViewState),
        // flatten them in memory instead of issuing a new query.
        if ($this->relationLoaded('rounds')) {
            return $this->rounds
                ->flatMap(fn (Round $round) => $round->relationLoaded('turns')
                    ? $round->turns
                    : $round->turns()->get()
                )
                ->sortBy(fn (Turn $turn) => [
                    (int) ($turn->round?->phase_order ?? 0),
                    (string) $turn->created_at,
                    (string) $turn->id,
                ])
                ->values();
        }

        return Turn::query()
            ->whereIn('round_id', $this->rounds()->pluck('id'))
            ->with('round')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Turn $turn) => [
                (int) ($turn->round?->phase_order ?? 0),
                (string) $turn->created_at,
                (string) $turn->id,
            ])
            ->values();
    }

    /**
     * Returns the full transcript as a flat string for Groq reasoning context,
     * with each turn labeled with its phase (tournament mode only).
     *
     * The speakers are labelled by the team they speak for — the
     * Government team and the Opposition team — rather than as "You" and
     * "AI Opponent". A model that reads "You (government)" in a
     * transcript it was told it was writing has to work out, every time
     * it reads a turn, which of the two voices "You" is. Naming the
     * teams makes the transcript read the way the round was actually
     * fought, and makes "the opponent's last speech" mean one specific
     * thing.
     */
    public function buildTranscript(): string
    {
        $lines = [];

        foreach ($this->allTurns() as $turn) {
            $speaker = $turn->speaker === 'user'
                ? $this->sideLabel($this->user_side)
                : $this->sideLabel($this->aiSide());

            $phaseLabel = $turn->round?->phase
                ? '['.strtoupper($turn->round->phase).' PHASE] '
                : '';

            $lines[] = "{$phaseLabel}{$speaker}: {$turn->transcript}";
        }

        return implode("\n\n", $lines);
    }

    /**
     * The role a side speaks under, in the language of the debate.
     *
     * The transcript is read by the model, so its labels are in the
     * language the model is writing in. A Bangla debate's transcript is
     * labelled in Bangla, and a model reading it does not have to work
     * out which voice is which from a title in another language.
     */
    public function sideLabel(string $side): string
    {
        $language = $this->language ?: 'en';

        $label = config('debate.side_labels.'.$language.'.'.$side.'.role');

        if (is_string($label) && $label !== '') {
            return $label;
        }

        $label = config('debate.side_labels.en.'.$side.'.role');

        return is_string($label) && $label !== '' ? $label : ucfirst($side);
    }
}
