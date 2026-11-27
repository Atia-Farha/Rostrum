<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Exceptions\SpeechToTextProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ElevenLabsSpeechToTextService
{
    public function __construct(
        private readonly string $baseUrl = 'https://api.elevenlabs.io/v1',
    ) {}

    /** @throws SpeechToTextProviderException */
    public function transcribe(string $audioFilePath, ?string $language = null): string
    {
        $apiKey = trim((string) config('services.elevenlabs.key'));
        $model = trim((string) config('services.elevenlabs.stt.model'));
        if ($apiKey === '' || $model === '') {
            throw new SpeechToTextProviderException('ElevenLabs speech-to-text is not configured.', false);
        }

        $stream = fopen($audioFilePath, 'r');
        if ($stream === false) {
            throw new SpeechToTextProviderException('Unable to read the recorded audio.', false);
        }

        try {
            try {
                $response = Http::withHeaders(['xi-api-key' => $apiKey])
                    ->timeout((int) config('services.elevenlabs.stt.timeout'))
                    ->attach('file', $stream, basename($audioFilePath))
                    ->post("{$this->baseUrl}/speech-to-text", [
                        'model_id' => $model,
                        'language_code' => $language === 'bn' ? 'ben' : 'eng',
                    ]);
            } catch (ConnectionException $exception) {
                throw new SpeechToTextProviderException('ElevenLabs speech-to-text is temporarily unavailable.', true);
            }
        } finally {
            fclose($stream);
        }

        if ($response->failed()) {
            $retryable = $response->status() === 408 || $response->status() === 429 || $response->serverError();
            throw new SpeechToTextProviderException('ElevenLabs speech-to-text failed.', $retryable);
        }

        $text = trim((string) $response->json('text', ''));
        if ($text === '') {
            throw new SpeechToTextProviderException('ElevenLabs returned an empty transcription.', false);
        }

        return $text;
    }
}
