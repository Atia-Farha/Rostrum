<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Debate extends Model
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

    /**
     * Returns all turns across all rounds, ordered chronologically.
     *
     * The ordering is by round, then by time, then by id. Turns in one
     * round are often written within the same second — the user speaks
     * and the AI answers immediately — and ordering only by created_at
     * leaves the database free to return those two in either order. A
     * transcript that puts the AI's answer before the speech it was
     * answering is worse than useless: it teaches the model that the
     * opponent replies to things nobody has said yet.
     */
    public function allTurns()
    {
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
     * Returns the full transcript as a flat string for Gemini context,
     * with each turn labeled with its phase (tournament mode only).
     *
     * The speakers are labelled by the role they speak under — the
     * Prime Minister and the Leader of the Opposition — rather than as
     * "You" and "AI Opponent". A model that reads "You (government)" in
     * a transcript it was told it was writing has to work out, every
     * time it reads a turn, which of the two voices "You" is. Naming
     * the roles makes the transcript read the way the round was
     * actually fought, and makes "the opponent's last speech" mean one
     * specific thing.
     */
    public function buildTranscript(): string
    {
        $lines = [];

        foreach ($this->allTurns() as $turn) {
            $speaker = $turn->speaker === 'user'
                ? $this->sideLabel($this->user_side)
                : $this->sideLabel($this->aiSide());

            $phaseLabel = $turn->round?->phase
                ? '[' . strtoupper($turn->round->phase) . ' PHASE] '
                : '';

            $lines[] = "{$phaseLabel}{$speaker}: {$turn->transcript}";
        }

        return implode("\n\n", $lines);
    }

    /**
     * The role a side speaks under.
     */
    public function sideLabel(string $side): string
    {
        return config('debate.side_labels.'.$side.'.role', ucfirst($side));
    }
}
