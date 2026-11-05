<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ElevenLabsService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.elevenlabs.io/v1';

    /**
     * Resolved models per voice, for the lifetime of this service instance.
     *
     * A voice's model is a property of the voice, not of the language, and
     * the API has to be asked once to learn it. The service is resolved per
     * request, so this cache lives for a single request at most.
     *
     * @var array<string, list<string>>
     */
    private array $modelByVoice = [];

    public function __construct()
    {
        $this->apiKey = (string) config('services.elevenlabs.key', env('ELEVENLABS_API_KEY', ''));
    }

    /**
     * Synthesize text to speech for a given persona voice.
     *
     * Stores the audio file on the PRIVATE disk and returns the file NAME.
     * The caller turns that into a URL; the file is streamed back through
     * DebateController::audio(), which checks debate ownership, so a debate's
     * audio is never world-readable off the public disk.
     *
     * The text is trimmed to fit the account's remaining character quota so a
     * speech is always audible even when credits run low. The full transcript
     * is unaffected — only the TTS payload is shortened.
     *
     * @throws \RuntimeException on API failure
     */
    public function synthesize(string $text, string $voiceId, string $language): string
    {
        $models = $this->modelsFor($voiceId);

        $text = $this->trimToSentenceBoundary($text, (int) config('debate.tts_max_chars', 1500));

        $response = null;
        $usedModel = $models[0];

        foreach ($models as $model) {
            $response = $this->callTts($text, $voiceId, $model);

            if ($response->successful()) {
                $usedModel = $model;
                break;
            }

            // This account is not entitled to the voice's own model. The
            // voice still has to speak, so try the next candidate — the
            // general model is the last one and always works.
            if ($response->status() === 401 || $response->status() === 403) {
                Log::warning('ElevenLabs model not available to this account — trying the next one', [
                    'voice_id' => $voiceId,
                    'model_id' => $model,
                    'status'   => $response->status(),
                ]);
                continue;
            }

            break;
        }

        // Quota exhausted — retry once with a text that fits the remaining credits
        if ($response && $response->failed() && str_contains($response->body(), 'quota_exceeded')) {
            $shorter = $this->trimToCreditBudget($text, $response->body());
            if ($shorter !== $text) {
                Log::warning('ElevenLabs TTS quota — retrying with trimmed text', [
                    'original_chars' => mb_strlen($text),
                    'trimmed_chars'  => mb_strlen($shorter),
                ]);
                $response = $this->callTts($shorter, $voiceId, $usedModel);
            }
        }

        if (! $response || $response->failed()) {
            Log::error('ElevenLabs TTS failed', [
                'status'   => $response?->status(),
                'body'     => $response?->body(),
                'voice_id' => $voiceId,
                'model_id' => $usedModel,
            ]);
            throw new \RuntimeException('ElevenLabs TTS API call failed: ' . ($response?->status() ?? 'no response'));
        }

        // Store the audio file on the private disk. We return only the file
        // name — the caller builds the URL, so the file keeps working no
        // matter which host/port serves the app (absolute URLs bake in
        // APP_URL and break on other machines).
        $filename = 'audio/' . Str::uuid() . '.mp3';
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        $disk->put($filename, $response->body());

        return basename($filename);
    }

    /**
     * The models to speak this voice with, best first.
     *
     * A cloned voice carries its character in the model it was fine-tuned
     * on. ElevenLabs reports those models on the voice itself, and the
     * language the voice was verified in sits with them. Using a general
     * model instead — eleven_multilingual_v2 — does not fail: the request
     * succeeds and the Bangla is pronounced correctly. But the rendering
     * falls back to that model's own generic delivery, which is flat and
     * narrow in pitch, and the voice stops sounding like the person it
     * was cloned from. That is why a Bangla debate can use the right voice
     * ID and still sound like no voice in particular.
     *
     * So the model is a property of the voice and is looked up, not
     * assumed from the language. The list ends with the general model, so
     * the voice always has something to speak with even if the account
     * cannot reach the voice's own models.
     *
     * @return list<string>
     */
    private function modelsFor(string $voiceId): array
    {
        if (isset($this->modelByVoice[$voiceId])) {
            return $this->modelByVoice[$voiceId];
        }

        $fallback = (string) config('debate.elevenlabs_model', 'eleven_multilingual_v2');

        $response = Http::withHeaders(['xi-api-key' => $this->apiKey])
            ->timeout(30)
            ->get("{$this->baseUrl}/voices/{$voiceId}");

        if ($response->failed()) {
            Log::warning('Could not read ElevenLabs voice metadata — using the default model', [
                'voice_id' => $voiceId,
                'status'   => $response->status(),
            ]);

            return $this->modelByVoice[$voiceId] = [$fallback];
        }

        return $this->modelByVoice[$voiceId] = $this->candidateModels($response->json(), $fallback);
    }

    /**
     * Order the models a voice can be spoken with, best first.
     *
     * The fine-tuned models are listed on the voice, and the languages the
     * voice was verified in are listed against the models it was verified
     * for. A model the voice was verified in for this language comes
     * first; the voice's other fine-tuned models come next, because they
     * are still the voice's own delivery. The general model is last.
     *
     * @param array<string, mixed> $voice
     * @return list<string>
     */
    private function candidateModels(array $voice, string $fallback): array
    {
        $baseModels = $voice['high_quality_base_model_ids'] ?? [];

        if (! is_array($baseModels) || $baseModels === []) {
            return [$fallback];
        }

        $language = (string) config('debate.tts_language', 'bn');

        $models = [];

        // A model the voice was verified in for the language being spoken.
        foreach ((array) ($voice['verified_languages'] ?? []) as $entry) {
            if (($entry['language'] ?? null) !== $language) {
                continue;
            }
            $modelId = $entry['model_id'] ?? null;
            if ($modelId && in_array($modelId, $baseModels, true) && ! in_array($modelId, $models, true)) {
                $models[] = $modelId;
            }
        }

        // The voice's own fine-tuned models. Some are only on higher plans
        // (eleven_v4_hq, eleven_v4_post_trained), so the synthesiser falls
        // through them if the account is not entitled to one.
        foreach ($baseModels as $modelId) {
            if (! in_array($modelId, $models, true)) {
                $models[] = $modelId;
            }
        }

        // The general model is the last resort — it always works, but it
        // flattens the voice into a generic delivery.
        if (! in_array($fallback, $models, true)) {
            $models[] = $fallback;
        }

        return $models;
    }

    /**
     * Call the ElevenLabs text-to-speech endpoint.
     */
    private function callTts(string $text, string $voiceId, string $model): \Illuminate\Http\Client\Response
    {
        return Http::withHeaders([
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

        $request = Http::withHeaders(['xi-api-key' => $this->apiKey])
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
