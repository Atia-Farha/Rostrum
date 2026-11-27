<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Exceptions\TtsProviderException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EdgeTtsService
{
    public function synthesize(string $text, string $language): string
    {
        $binary = trim((string) config('services.edge_tts.binary'));
        $voice = trim((string) config($language === 'bn'
            ? 'services.edge_tts.voices.bn'
            : 'services.edge_tts.voices.en'));
        if ($binary === '' || $voice === '') {
            throw new \LogicException('Missing required TTS configuration: Edge TTS binary or voice.');
        }

        $filename = 'audio/edge_tts_'.Str::uuid().'.mp3';
        $disk = Storage::disk('local');
        $disk->makeDirectory('audio');
        $path = $disk->path($filename);

        $result = Process::timeout((int) config('services.edge_tts.timeout'))
            ->run([$binary, '--no-progress', '--voice', $voice, '--text', $text, '--write-media', $path]);

        if ($result->failed() || ! $disk->exists($filename) || $disk->size($filename) === 0) {
            $disk->delete($filename);
            throw new TtsProviderException('Microsoft Edge TTS is unavailable.', false);
        }

        return basename($filename);
    }
}
