<?php

namespace Tests\Feature;

use App\Services\ElevenLabsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A cloned voice is spoken with the model it was trained on.
 *
 * The Bangla voices in this app are clones. A clone's character lives in the
 * model it was fine-tuned on, and ElevenLabs reports that model on the
 * voice itself. Rendering a clone through a general model such as
 * eleven_multilingual_v2 does not fail — the request succeeds, the Bangla
 * is pronounced correctly, and the file plays. But the delivery falls back
 * to the general model's own generic reading, so the voice stops sounding
 * like the person it was cloned from. Measured on the same text and the
 * same voice, the general model produced a pitch spread of 29 Hz against
 * the voice's own 120 Hz, which is the difference between a voice and a
 * reading.
 *
 * These tests hold the model choice: the voice's own model comes first,
 * and the general model is only ever the last resort.
 */
class ElevenLabsModelSelectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The voice metadata ElevenLabs returns for a cloned Bangla voice.
     */
    private function clonedVoiceMetadata(string $voiceId = 'd532cgnzlrx7AWL3CD9i'): array
    {
        return [
            'voice_id'  => $voiceId,
            'name'      => 'Fahad Mir - Casual',
            'category'  => 'professional',
            'labels'    => ['language' => 'bn', 'gender' => 'male'],
            'high_quality_base_model_ids' => [
                'eleven_v4',
                'eleven_v4_hq',
                'eleven_v4_post_trained',
                'eleven_v4_turbo',
            ],
            'verified_languages' => [
                ['language' => 'bn', 'model_id' => 'eleven_v4'],
                ['language' => 'bn', 'model_id' => 'eleven_v4_hq'],
                ['language' => 'bn', 'model_id' => 'eleven_v4_turbo'],
            ],
        ];
    }

    /**
     * The model a cloned voice is spoken with.
     */
    private function modelFor(array $metadata): string
    {
        $service = app(ElevenLabsService::class);

        $method = new \ReflectionMethod($service, 'candidateModels');
        $method->setAccessible(true);

        return $method->invoke($service, $metadata, 'eleven_multilingual_v2')[0];
    }

    /**
     * A cloned voice is spoken with the model it was fine-tuned on.
     *
     * This is the whole point of the change: a clone rendered through a
     * general model sounds like no voice at all.
     */
    public function test_a_cloned_voice_is_spoken_with_its_own_fine_tuned_model(): void
    {
        $this->assertSame('eleven_v4', $this->modelFor($this->clonedVoiceMetadata()));
    }

    /**
     * A premade voice has no model of its own, so the general model serves it.
     */
    public function test_a_premade_voice_uses_the_general_model(): void
    {
        $premade = [
            'voice_id' => 'pNInz6obpgDQGcFmaJgB',
            'category' => 'premade',
            'high_quality_base_model_ids' => [],
            'verified_languages' => [],
        ];

        $this->assertSame('eleven_multilingual_v2', $this->modelFor($premade));
    }

    /**
     * The general model is always available as a last resort.
     *
     * Some of the voice's models are only on higher plans — eleven_v4_hq
     * and eleven_v4_post_trained both return "model_access_denied" on a
     * free plan. The voice still has to speak, so the general model closes
     * the list.
     */
    public function test_the_general_model_is_always_the_last_resort(): void
    {
        $service = app(ElevenLabsService::class);

        $method = new \ReflectionMethod($service, 'candidateModels');
        $method->setAccessible(true);

        $models = $method->invoke($service, $this->clonedVoiceMetadata(), 'eleven_multilingual_v2');

        $this->assertSame('eleven_multilingual_v2', end($models));
    }

    /**
     * A model the account cannot reach does not stop the voice.
     *
     * The synthesiser walks the list and moves on when a model is denied,
     * so an account without eleven_v4_hq still gets the voice's own
     * delivery from eleven_v4.
     */
    public function test_synthesis_falls_through_to_the_next_model_when_one_is_denied(): void
    {
        Http::fake([
            'api.elevenlabs.io/v1/voices/*' => Http::response($this->clonedVoiceMetadata()),
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::sequence()
                ->push(['detail' => ['code' => 'model_access_denied']], 401)
                ->push('audio-bytes', 200),
        ]);

        config(['services.elevenlabs.key' => 'test-key']);

        $service = app(ElevenLabsService::class);

        $name = $service->synthesize('আমি একটি উদাহরণ।', 'd532cgnzlrx7AWL3CD9i', 'bn');

        $this->assertFileExists(storage_path('app/private/audio/' . $name));

        // Two synthesis requests: the denied model, then the next one.
        $ttsRequests = Http::recorded(fn ($request) => str_contains($request->url(), '/text-to-speech/'));
        $this->assertCount(2, $ttsRequests);
    }

    /**
     * The voice's own model is what actually reaches the API.
     */
    public function test_the_request_carries_the_cloned_voice_own_model(): void
    {
        Http::fake([
            'api.elevenlabs.io/v1/voices/*' => Http::response($this->clonedVoiceMetadata()),
            'api.elevenlabs.io/v1/text-to-speech/*' => Http::response('audio-bytes', 200),
        ]);

        config(['services.elevenlabs.key' => 'test-key']);

        app(ElevenLabsService::class)->synthesize('আমি একটি উদাহরণ।', 'd532cgnzlrx7AWL3CD9i', 'bn');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.elevenlabs.io/v1/text-to-speech/d532cgnzlrx7AWL3CD9i'
                && ($request->data()['model_id'] ?? null) === 'eleven_v4';
        });
    }
}
