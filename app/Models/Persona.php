<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Persona extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'name',
        'description',
        'system_prompt',
        'elevenlabs_voice_id',
    ];

    public function voiceName(): string
    {
        return match($this->elevenlabs_voice_id) {
            'pNInz6obpgDQGcFmaJgB' => 'Adam',
            'EXAVITQu4vr4xnSDxMaL' => 'Bella',
            'TX3LPaxmHKxFdv7VOQHJ' => 'Liam',
            default               => 'Voice',
        };
    }

    public function debates(): HasMany
    {
        return $this->hasMany(Debate::class);
    }
}
