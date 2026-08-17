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
     */
    public function allTurns()
    {
        return Turn::whereIn('round_id', $this->rounds()->pluck('id'))
            ->with('round')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Returns the full transcript as a flat string for Gemini context,
     * with each turn labeled with its phase (tournament mode only).
     */
    public function buildTranscript(): string
    {
        $lines = [];
        foreach ($this->allTurns() as $turn) {
            $speaker = $turn->speaker === 'user'
                ? 'You (' . $this->user_side . ')'
                : 'AI Opponent (' . $this->aiSide() . ')';
            $phaseLabel = $turn->round?->phase
                ? '[' . strtoupper($turn->round->phase) . ' PHASE] '
                : '';
            $lines[] = "{$phaseLabel}{$speaker}: {$turn->transcript}";
        }
        return implode("\n\n", $lines);
    }
}
