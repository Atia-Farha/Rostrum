<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Exceptions\SpeechToTextProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GroqSpeechToTextService
{
    /** @throws SpeechToTextProviderException */
    public function transcribe(string $audioFilePath, string $language, string $model): string
    {
        $apiKey = trim((string) config('services.groq.key'));
        if ($apiKey === '' || trim($model) === '') {
            throw new SpeechToTextProviderException('Groq speech-to-text is not configured.', false);
        }

        $stream = fopen($audioFilePath, 'r');
        if ($stream === false) {
            throw new SpeechToTextProviderException('Unable to read the recorded audio.', false);
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) config('services.groq.stt.timeout'))
                ->attach('file', $stream, basename($audioFilePath))
                ->post(rtrim((string) config('services.groq.base_url'), '/').'/audio/transcriptions', [
                    'model' => $model,
                    'language' => $language,
                    'response_format' => 'json',
                ]);
        } catch (ConnectionException $exception) {
            throw new SpeechToTextProviderException('Groq speech-to-text is temporarily unavailable.', true);
        } finally {
            fclose($stream);
        }

        if ($response->failed()) {
            $retryable = $response->status() === 408 || $response->status() === 429 || $response->serverError();
            throw new SpeechToTextProviderException('Groq speech-to-text failed.', $retryable);
        }

        $text = trim((string) $response->json('text', ''));
        if ($text === '') {
            throw new SpeechToTextProviderException('Groq returned an empty transcription.', false);
        }

        return $text;
    }
}
