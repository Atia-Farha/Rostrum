<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Free, unlimited TTS fallback using the public Google Translate text-to-speech
 * endpoint — pure PHP (no Python, no API key). Text longer than ~190 chars is
 * chunked at sentence boundaries and the MP3 fragments are concatenated.
 */
class GoogleTtsService
{
    private const MAX_CHUNK = 190;

    /**
     * Synthesize speech via the Google Translate TTS endpoint.
     * Stores the audio in storage/app/public/audio/ and returns its public URL.
     *
     * @throws \RuntimeException on HTTP failure
     */
    public function synthesize(string $text, string $language): string
    {
        $lang   = $language === 'bn' ? 'bn' : 'en';
        $chunks = $this->chunkText($text);

        $audio = '';
        foreach ($chunks as $index => $chunk) {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
                'Referer'    => 'https://translate.google.com/',
            ])
                ->timeout(30)
                ->get('https://translate.google.com/translate_tts', [
                    'ie'     => 'UTF-8',
                    'client' => 'tw-ob',
                    'q'      => $chunk,
                    'tl'     => $lang,
                    'total'  => count($chunks),
                    'idx'    => $index,
                ]);

            if ($response->failed()) {
                Log::error('Google TTS chunk failed', [
                    'status'   => $response->status(),
                    'body'     => mb_substr($response->body(), 0, 300),
                    'language' => $lang,
                ]);
                throw new \RuntimeException('Google TTS failed: ' . $response->status());
            }

            $audio .= $response->body();

            // Be gentle — Google rate-limits rapid bursts
            if ($index < count($chunks) - 1) {
                usleep(250000);
            }
        }

        $filename = 'audio/google_tts_' . Str::uuid() . '.mp3';
        Storage::disk('public')->put($filename, $audio);

        // Relative URL — portable across hosts (see ElevenLabsService).
        return '/storage/' . $filename;
    }

    /**
     * Split text into <= MAX_CHUNK-character pieces at sentence boundaries.
     */
    private function chunkText(string $text): array
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) === 0) {
            return [];
        }
        if (mb_strlen($text) <= self::MAX_CHUNK) {
            return [$text];
        }

        $chunks = [];
        while (mb_strlen($text) > self::MAX_CHUNK) {
            $part = mb_substr($text, 0, self::MAX_CHUNK);
            $cut = mb_strrpos($part, '. ') ?: (mb_strrpos($part, '! ') ?: (mb_strrpos($part, '? ') ?: (mb_strrpos($part, ', ') ?: false)));

            if ($cut === false || $cut < self::MAX_CHUNK / 2) {
                $lastSpace = mb_strrpos($part, ' ');
                $cut       = ($lastSpace !== false && $lastSpace > self::MAX_CHUNK / 2) ? $lastSpace : self::MAX_CHUNK;
            }

            $chunks[] = mb_substr($text, 0, $cut + 1);
            $text     = trim(mb_substr($text, $cut + 1));
        }

        if (mb_strlen($text) > 0) {
            $chunks[] = $text;
        }

        return $chunks;
    }
}
