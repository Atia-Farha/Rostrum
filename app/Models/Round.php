<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Round extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'debate_id',
        'phase',
        'phase_order',
    ];

    public function debate(): BelongsTo
    {
        return $this->belongsTo(Debate::class);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(Turn::class)->orderBy('created_at');
    }
}
