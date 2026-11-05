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
        'elevenlabs_voice_id_bn',
    ];

    /**
     * The display name for each voice the app speaks with.
     *
     * Keyed by voice ID rather than by persona, because the badge in the
     * podium answers "who is talking to me" — and the answer is a voice,
     * not a persona. A persona may end up with a different voice again
     * later; the name follows the voice.
     */
    private const VOICE_NAMES = [
        // English
        'pNInz6obpgDQGcFmaJgB' => 'Adam',
        'EXAVITQu4vr4xnSDxMaL' => 'Bella',
        'TX3LPaxmHKxFdv7VOQHJ' => 'Liam',

        // Bangla
        'd532cgnzlrx7AWL3CD9i' => 'Fahad Mir',
        'vvOYlSJHf8lTWIG4tDNP' => 'Rayhan',
        'VizHiwkPO7hXc967v4tE' => 'Rakib',
    ];

    /**
     * The ElevenLabs voice this persona speaks with in the given language.
     *
     * Each persona carries an English voice and a Bangla voice. The
     * Bangla voices are native recordings, so a Bangla debate sounds like
     * Bangla rather than like English read aloud slowly.
     *
     * A persona with no Bangla voice falls back to its English one. That
     * keeps synthesis working — a slightly wrong accent is better than no
     * audio at all — and it means adding a persona without a Bangla voice
     * cannot break the debate.
     */
    public function voiceId(string $language = 'en'): string
    {
        if ($language === 'bn' && ! empty($this->elevenlabs_voice_id_bn)) {
            return $this->elevenlabs_voice_id_bn;
        }

        return $this->elevenlabs_voice_id;
    }

    /**
     * The display name of the voice this persona speaks with.
     *
     * Falls back to a neutral label for a voice the app does not know by
     * name — the badge should still say something, and the voice ID is
     * better than a wrong name.
     */
    public function voiceName(string $language = 'en'): string
    {
        $voiceId = $this->voiceId($language);

        return self::VOICE_NAMES[$voiceId] ?? 'Voice';
    }

    public function debates(): HasMany
    {
        return $this->hasMany(Debate::class);
    }
}
