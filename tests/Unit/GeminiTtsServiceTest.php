<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Exceptions\TtsProviderException;
use App\Services\GeminiTtsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class GeminiTtsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.tts.model' => 'configured-tts-model',
            'services.gemini.tts.voices.en' => 'Kore',
            'services.gemini.tts.voices.bn' => 'Puck',
            'services.gemini.tts.timeout' => 30,
        ]);
    }

    public function test_it_sends_verbatim_text_to_the_configured_gemini_model_and_stores_wav_audio(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'steps' => [['type' => 'model_output', 'content' => [[
                    'type' => 'audio', 'data' => base64_encode('RIFF-wav-bytes'),
                ]]]],
            ]),
        ]);

        $name = app(GeminiTtsService::class)->synthesize('মূল বক্তব্য অপরিবর্তিত থাকবে।', 'bn');

        Storage::disk('local')->assertExists('audio/'.$name);
        $this->assertStringStartsWith('gemini_tts_', $name);
        $this->assertStringEndsWith('.wav', $name);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/interactions'
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && ($request['model'] ?? null) === 'configured-tts-model'
                && ($request['input'][0]['content'][0]['text'] ?? null) === 'মূল বক্তব্য অপরিবর্তিত থাকবে।'
                && ($request['generation_config']['speech_config'][0]['voice'] ?? null) === 'Puck';
        });
    }

    public function test_it_marks_provider_failures_as_eligible_for_edge_fallback(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        try {
            app(GeminiTtsService::class)->synthesize('A response.', 'en');
            $this->fail('Expected the Gemini service to fail.');
        } catch (TtsProviderException $exception) {
            $this->assertTrue($exception->shouldFallback);
        }
    }

    public function test_it_rejects_an_invalid_audio_payload_as_eligible_for_edge_fallback(): void
    {
        Http::fake([
            '*' => Http::response([
                'steps' => [['type' => 'model_output', 'content' => [[
                    'type' => 'audio', 'data' => base64_encode('not a wav file'),
                ]]]],
            ]),
        ]);

        try {
            app(GeminiTtsService::class)->synthesize('A response.', 'en');
            $this->fail('Expected the invalid audio payload to fail.');
        } catch (TtsProviderException $exception) {
            $this->assertTrue($exception->shouldFallback);
        }
    }
}
