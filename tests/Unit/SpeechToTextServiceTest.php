<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ElevenLabsSpeechToTextService;
use App\Services\Exceptions\SpeechToTextProviderException;
use App\Services\GroqSpeechToTextService;
use App\Services\SpeechToTextService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

final class SpeechToTextServiceTest extends TestCase
{
    public function test_groq_sends_the_selected_model_and_original_language(): void
    {
        config()->set('services.groq.key', 'test-key');
        config()->set('services.groq.base_url', 'https://api.groq.test/openai/v1');
        config()->set('services.groq.stt.timeout', 10);
        Http::fake(['https://api.groq.test/*' => Http::response(['text' => 'বাংলা বক্তব্য'])]);

        $audioPath = tempnam(sys_get_temp_dir(), 'stt-');
        file_put_contents($audioPath, 'audio');

        try {
            $text = (new GroqSpeechToTextService)->transcribe($audioPath, 'bn', 'primary-whisper');
        } finally {
            @unlink($audioPath);
        }

        $this->assertSame('বাংলা বক্তব্য', $text);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.groq.test/openai/v1/audio/transcriptions'
            && $request->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_it_uses_the_primary_groq_model_first(): void
    {
        config()->set('services.groq.stt.models', ['primary-whisper', 'turbo-whisper']);

        $groq = Mockery::mock(GroqSpeechToTextService::class);
        $groq->shouldReceive('transcribe')->once()
            ->with('/tmp/speech.webm', 'bn', 'primary-whisper')
            ->andReturn('বাংলা বক্তব্য');
        $elevenLabs = Mockery::mock(ElevenLabsSpeechToTextService::class);
        $elevenLabs->shouldNotReceive('transcribe');

        $result = (new SpeechToTextService($groq, $elevenLabs))->transcribe('/tmp/speech.webm', 'bn');

        $this->assertSame('বাংলা বক্তব্য', $result->text);
        $this->assertSame('groq_whisper_large_v3', $result->provider);
    }

    public function test_it_falls_back_in_the_required_order(): void
    {
        config()->set('services.groq.stt.models', ['primary-whisper', 'turbo-whisper']);

        $groq = Mockery::mock(GroqSpeechToTextService::class);
        $groq->shouldReceive('transcribe')->once()->with('/tmp/speech.webm', 'en', 'primary-whisper')
            ->andThrow(new SpeechToTextProviderException('Groq unavailable.', true));
        $groq->shouldReceive('transcribe')->once()->with('/tmp/speech.webm', 'en', 'turbo-whisper')
            ->andThrow(new SpeechToTextProviderException('Groq unavailable.', true));
        $elevenLabs = Mockery::mock(ElevenLabsSpeechToTextService::class);
        $elevenLabs->shouldReceive('transcribe')->once()->with('/tmp/speech.webm', 'en')
            ->andReturn('English speech');

        $result = (new SpeechToTextService($groq, $elevenLabs))->transcribe('/tmp/speech.webm', 'en');

        $this->assertSame('English speech', $result->text);
        $this->assertSame('elevenlabs', $result->provider);
    }
}
