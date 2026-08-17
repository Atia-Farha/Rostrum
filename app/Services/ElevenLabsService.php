<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ElevenLabsService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://api.elevenlabs.io/v1';

    public function __construct()
    {
        $this->apiKey = (string) config('services.elevenlabs.key', env('ELEVENLABS_API_KEY', ''));
        $this->model  = (string) config('debate.elevenlabs_model', 'eleven_multilingual_v2');
    }

    /**
     * Synthesize text to speech for a given persona voice.
     * Stores the audio file in storage/app/public/audio/ and returns its public URL.
     *
     * The text is trimmed to fit the account's remaining character quota so a
     * speech is always audible even when credits run low. The full transcript
     * is unaffected — only the TTS payload is shortened.
     *
     * @throws \RuntimeException on API failure
     */
    public function synthesize(string $text, string $voiceId, string $language): string
    {
        // eleven_multilingual_v2 produces the best Bangla phonetics with any voice
        $model = $language === 'bn'
            ? 'eleven_multilingual_v2'
            : 'eleven_turbo_v2_5';

        $text = $this->trimToSentenceBoundary($text, (int) config('debate.tts_max_chars', 1500));

        $response = $this->callTts($text, $voiceId, $model);

        // Quota exhausted — retry once with a text that fits the remaining credits
        if ($response->failed() && str_contains($response->body(), 'quota_exceeded')) {
            $shorter = $this->trimToCreditBudget($text, $response->body());
            if ($shorter !== $text) {
                Log::warning('ElevenLabs TTS quota — retrying with trimmed text', [
                    'original_chars' => mb_strlen($text),
                    'trimmed_chars'  => mb_strlen($shorter),
                ]);
                $response = $this->callTts($shorter, $voiceId, $model);
            }
        }

        if ($response->failed()) {
            Log::error('ElevenLabs TTS failed', [
                'status'   => $response->status(),
                'body'     => $response->body(),
                'voice_id' => $voiceId,
            ]);
            throw new \RuntimeException('ElevenLabs TTS API call failed: ' . $response->status());
        }

        // Store the audio file locally. We store the RELATIVE URL so the file
        // keeps working no matter which host/port serves the app (absolute
        // URLs bake in APP_URL and break on other machines).
        $filename = 'audio/' . Str::uuid() . '.mp3';
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $disk->put($filename, $response->body());

        return '/storage/' . $filename;
    }

    /**
     * Call the ElevenLabs text-to-speech endpoint.
     */
    private function callTts(string $text, string $voiceId, string $model): \Illuminate\Http\Client\Response
    {
        return Http::withoutVerifying()
            ->withHeaders([
                'xi-api-key'   => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(60)
            ->post("{$this->baseUrl}/text-to-speech/{$voiceId}", [
                'text'     => $text,
                'model_id' => $model,
            ]);
    }

    /**
     * Trim text to at most $maxChars characters, breaking at the last
     * sentence boundary ('.', '?', '!') within the limit.
     */
    private function trimToSentenceBoundary(string $text, int $maxChars): string
    {
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        $trimmed = mb_substr($text, 0, $maxChars);

        $cut = -1;
        foreach (['. ', '? ', '! ', '.', '?', '!', "\n"] as $boundary) {
            $pos = mb_strrpos($trimmed, $boundary);
            if ($pos !== false && $pos > $maxChars * 0.5 && $pos > $cut) {
                $cut = $pos;
            }
        }

        return mb_substr($text, 0, $cut > 0 ? $cut + 1 : $maxChars);
    }

    /**
     * Parse the remaining credit count from an ElevenLabs quota_exceeded body.
     */
    private function parseRemainingCredits(string $body): int
    {
        if (preg_match('/You have (\d+) credits remaining/i', $body, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * Compute a text length that fits the remaining credits and trim to it.
     * Uses the error body's "required credits" to derive the per-character cost.
     */
    private function trimToCreditBudget(string $text, string $body): string
    {
        $remaining = $this->parseRemainingCredits($body);
        if ($remaining <= 0 || $text === '') {
            return $text;
        }

        // Cost per character from the failed request (already-trimmed text)
        $creditsPerChar = 1.0;
        if (preg_match('/while (\d+) credits are required/i', $body, $m)) {
            $required = (int) $m[1];
            $creditsPerChar = $required / max(1, mb_strlen($text));
        }

        // 15% safety margin below the remaining budget
        $budgetChars = (int) floor(($remaining / max(0.5, $creditsPerChar)) * 0.85);

        return $this->trimToSentenceBoundary($text, max(1, $budgetChars));
    }

    /**
     * Transcribe user audio using ElevenLabs Scribe STT API.
     *
     * @param string $audioFilePath Absolute path to the local audio file (.webm or .mp3)
     * @param string|null $language ISO language code (e.g., 'en', 'bn')
     * @return string The transcribed text
     */
    public function transcribe(string $audioFilePath, ?string $language = null): string
    {
        $fileStream = fopen($audioFilePath, 'r');

        $langCode = $language === 'bn' ? 'ben' : 'eng';

        $params = ['model_id' => 'scribe_v1'];
        if ($langCode) {
            $params['language_code'] = $langCode;
        }

        $request = Http::withoutVerifying()
            ->withHeaders(['xi-api-key' => $this->apiKey])
            ->timeout(60)
            ->attach('file', $fileStream, basename($audioFilePath))
            ->post("{$this->baseUrl}/speech-to-text", $params);

        if (is_resource($fileStream)) {
            fclose($fileStream);
        }

        if ($request->failed()) {
            Log::error('ElevenLabs Scribe STT failed', [
                'status' => $request->status(),
                'body'   => $request->body(),
            ]);
            throw new \RuntimeException('ElevenLabs Scribe STT failed: ' . $request->status());
        }

        return $request->json('text', '');
    }
}
