<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Exceptions\SpeechToTextProviderException;
use App\Services\Exceptions\SpeechToTextUnavailableException;
use Illuminate\Support\Facades\Log;

final class SpeechToTextService
{
    public function __construct(
        private readonly GroqSpeechToTextService $groq,
        private readonly ElevenLabsSpeechToTextService $elevenLabs,
    ) {}

    /** @throws SpeechToTextUnavailableException */
    public function transcribe(string $audioFilePath, string $language): SpeechToTextResult
    {
        $models = config('services.groq.stt.models', []);
        $primaryModel = is_string($models[0] ?? null) ? $models[0] : '';
        $fallbackModel = is_string($models[1] ?? null) ? $models[1] : '';
        $providers = [
            ['name' => 'groq_whisper_large_v3', 'callback' => fn (): string => $this->groq->transcribe($audioFilePath, $language, $primaryModel)],
            ['name' => 'groq_whisper_large_v3_turbo', 'callback' => fn (): string => $this->groq->transcribe($audioFilePath, $language, $fallbackModel)],
            ['name' => 'elevenlabs', 'callback' => fn (): string => $this->elevenLabs->transcribe($audioFilePath, $language)],
        ];

        foreach ($providers as $provider) {
            try {
                return new SpeechToTextResult($provider['callback'](), $provider['name']);
            } catch (SpeechToTextProviderException $exception) {
                Log::warning('Speech-to-text provider failed.', [
                    'provider' => $provider['name'],
                    'fallback' => $exception->shouldFallback,
                ]);

                if (! $exception->shouldFallback) {
                    throw new SpeechToTextUnavailableException($exception->getMessage());
                }
            }
        }

        throw new SpeechToTextUnavailableException('Speech-to-text providers are temporarily unavailable.');
    }
}
