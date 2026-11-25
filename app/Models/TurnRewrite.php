<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TurnRewrite extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'turn_id',
        'original_text',
        'rewritten_text',
        'explanation_bullets',
    ];

    protected $casts = [
        'explanation_bullets' => 'array',
    ];

    public function turn(): BelongsTo
    {
        return $this->belongsTo(Turn::class);
    }
}
