<?php

namespace Tests\Feature;

use App\Models\Persona;
use Database\Seeders\PersonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A persona speaks in the language of the debate.
 *
 * A persona used to carry a single ElevenLabs voice, and that voice had to
 * serve both English and Bangla. The English premade voices read Bangla
 * passably but not well — the phonetics were built for English, so a Bangla
 * debate sounded like a translation read aloud by an English mouth.
 *
 * Each persona now carries a second voice: a native Bangla recording. The
 * tests below hold the parts that matter — a Bangla debate must pick the
 * Bangla voice, an English debate must keep the English one, and a persona
 * without a Bangla voice must still speak rather than fall silent.
 */
class PersonaVoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PersonaSeeder::class);
    }

    /**
     * The three seeded personas each carry both voices.
     */
    public function test_every_seeded_persona_has_an_english_and_a_bangla_voice(): void
    {
        $personas = Persona::all();

        $this->assertCount(3, $personas);

        foreach ($personas as $persona) {
            $this->assertNotEmpty($persona->elevenlabs_voice_id, "{$persona->name} has no English voice.");
            $this->assertNotEmpty($persona->elevenlabs_voice_id_bn, "{$persona->name} has no Bangla voice.");
        }
    }

    /**
     * The Bangla voices are the ones chosen for this app.
     *
     * They are native Bangla recordings from the ElevenLabs voice library,
     * chosen so that the voice matches the character the persona argues in:
     * calm for the logician, energetic for the cross-examiner, deep and
     * unsettling for the devil's advocate.
     */
    public function test_the_bangla_voices_are_the_chosen_ones(): void
    {
        $expected = [
            'Calm Logician'             => 'd532cgnzlrx7AWL3CD9i',
            'Aggressive Cross-Examiner' => 'vvOYlSJHf8lTWIG4tDNP',
            "Devil's Advocate"          => 'VizHiwkPO7hXc967v4tE',
        ];

        foreach ($expected as $name => $voiceId) {
            $persona = Persona::where('name', $name)->firstOrFail();

            $this->assertSame($voiceId, $persona->voiceId('bn'), "{$name} does not speak Bangla with the chosen voice.");
        }
    }

    /**
     * The English debate keeps the English voice.
     */
    public function test_an_english_debate_uses_the_english_voice(): void
    {
        $persona = Persona::where('name', 'Calm Logician')->firstOrFail();

        $this->assertSame('pNInz6obpgDQGcFmaJgB', $persona->voiceId('en'));
        $this->assertSame('Adam', $persona->voiceName('en'));
    }

    /**
     * The Bangla debate uses the Bangla voice.
     */
    public function test_a_bangla_debate_uses_the_bangla_voice(): void
    {
        $persona = Persona::where('name', 'Aggressive Cross-Examiner')->firstOrFail();

        $this->assertSame('vvOYlSJHf8lTWIG4tDNP', $persona->voiceId('bn'));
        $this->assertSame('Rayhan', $persona->voiceName('bn'));
    }

    /**
     * The badge names the voice, and the name follows the voice.
     *
     * The podium answers "who is talking to me", so the label belongs to
     * the voice rather than to the persona — a persona may be given a
     * different voice later, and the badge should then say so.
     */
    public function test_the_voice_badge_names_the_voice_in_use(): void
    {
        $persona = Persona::where('name', "Devil's Advocate")->firstOrFail();

        $this->assertSame('Liam', $persona->voiceName('en'));
        $this->assertSame('Rakib', $persona->voiceName('bn'));
    }

    /**
     * A persona with no Bangla voice still speaks.
     *
     * The column is nullable on purpose. A persona added without a Bangla
     * voice must fall back to its English one — a slightly wrong accent is
     * better than a debate that goes silent.
     */
    public function test_a_persona_without_a_bangla_voice_falls_back_to_its_english_voice(): void
    {
        $persona = Persona::where('name', 'Calm Logician')->firstOrFail();
        $persona->update(['elevenlabs_voice_id_bn' => null]);

        $this->assertSame('pNInz6obpgDQGcFmaJgB', $persona->voiceId('bn'));
        $this->assertSame('Adam', $persona->voiceName('bn'));
    }

    /**
     * An unknown voice still gets a label.
     *
     * The badge should say something even for a voice the app does not
     * know by name — a wrong name would be a lie, and silence would be a
     * hole in the interface.
     */
    public function test_an_unknown_voice_falls_back_to_a_neutral_label(): void
    {
        $persona = Persona::where('name', 'Calm Logician')->firstOrFail();
        $persona->update(['elevenlabs_voice_id' => 'some-brand-new-voice-id']);

        $this->assertSame('Voice', $persona->voiceName('en'));
    }

    /**
     * Re-seeding a persona applies a changed voice.
     *
     * The personas are the app's voice cast, and re-seeding is how a voice
     * change reaches a database that already has them. If the seeder
     * created the persona and left it alone, an old voice would sit in the
     * database and the change would appear to have been made but not to
     * have taken effect.
     */
    public function test_reseeding_applies_a_changed_voice(): void
    {
        $persona = Persona::where('name', 'Calm Logician')->firstOrFail();
        $persona->update(['elevenlabs_voice_id_bn' => 'stale-voice-id']);

        $this->seed(PersonaSeeder::class);

        $this->assertSame(
            'd532cgnzlrx7AWL3CD9i',
            Persona::where('name', 'Calm Logician')->firstOrFail()->voiceId('bn'),
        );
    }

    /**
     * The three personas do not share a voice in either language.
     *
     * If two personas spoke with the same voice, a reader could not tell
     * the opponents apart — which is the whole point of the persona.
     */
    public function test_the_personas_do_not_share_a_voice(): void
    {
        foreach (['en', 'bn'] as $language) {
            $voiceIds = Persona::pluck('elevenlabs_voice_id')
                ->merge(Persona::pluck('elevenlabs_voice_id_bn'))
                ->filter()
                ->unique();

            $this->assertCount(
                6,
                $voiceIds,
                "The personas should not share a voice in {$language}.",
            );
        }
    }
}
