<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Exceptions\TtsProviderException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeminiTtsService
{
    public function synthesize(string $text, string $language): string
    {
        $model = $this->requiredConfig('services.gemini.tts.model');
        $apiKey = $this->requiredConfig('services.gemini.key');
        $voice = $this->requiredConfig($language === 'bn'
            ? 'services.gemini.tts.voices.bn'
            : 'services.gemini.tts.voices.en');

        try {
            $response = Http::acceptJson()
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout((int) config('services.gemini.tts.timeout'))
                ->post('https://generativelanguage.googleapis.com/v1beta/interactions', [
                    'model' => $model,
                    'input' => [[
                        'type' => 'user_input',
                        'content' => [[
                            'type' => 'text',
                            'text' => $text,
                            'annotations' => [[
                                'type' => 'speech_metadata',
                                'style' => 'clear, natural, conversational debate speech',
                            ]],
                        ]],
                    ]],
                    'response_format' => ['type' => 'audio', 'mime_type' => 'audio/wav'],
                    'generation_config' => ['speech_config' => [['voice' => $voice]]],
                ]);
        } catch (\Throwable $exception) {
            throw new TtsProviderException('Gemini TTS is unavailable.', true, $exception);
        }

        if ($response->failed()) {
            throw new TtsProviderException(
                'Gemini TTS request failed with status '.$response->status().'.',
                in_array($response->status(), [408, 429, 500, 502, 503, 504], true),
            );
        }

        $audio = $this->audioFrom($response->json());
        if ($audio === null || ! str_starts_with($audio, 'RIFF')) {
            throw new TtsProviderException('Gemini TTS returned no playable audio.', true);
        }

        $filename = 'audio/gemini_tts_'.Str::uuid().'.wav';
        if (Storage::disk('local')->put($filename, $audio) === false) {
            throw new \RuntimeException('Unable to store Gemini TTS audio.');
        }

        return basename($filename);
    }

    /** @param array<string, mixed>|null $response */
    private function audioFrom(?array $response): ?string
    {
        foreach (array_reverse((array) ($response['steps'] ?? [])) as $step) {
            if (($step['type'] ?? null) !== 'model_output') {
                continue;
            }

            foreach (array_reverse((array) ($step['content'] ?? [])) as $content) {
                if (($content['type'] ?? null) === 'audio' && is_string($content['data'] ?? null)) {
                    $audio = base64_decode($content['data'], true);

                    return $audio === false ? null : $audio;
                }
            }
        }

        return null;
    }

    private function requiredConfig(string $key): string
    {
        $value = trim((string) config($key));
        if ($value === '') {
            throw new \LogicException("Missing required TTS configuration: {$key}.");
        }

        return $value;
    }
}
