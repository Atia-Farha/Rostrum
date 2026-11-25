<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Turn extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'round_id',
        'speaker',
        'transcript',
        'audio_path',
        'ai_move_type',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function rewrite(): HasOne
    {
        return $this->hasOne(TurnRewrite::class);
    }
}
