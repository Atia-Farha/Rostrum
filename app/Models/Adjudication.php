<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adjudication extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'debate_id',
        'matter_score',
        'manner_score',
        'method_score',
        'total_score',
        'fallacies',
        'feedback_bullets',
        'verdict',
    ];

    protected $casts = [
        'fallacies'        => 'array',
        'feedback_bullets' => 'array',
    ];

    public function debate(): BelongsTo
    {
        return $this->belongsTo(Debate::class);
    }
}
