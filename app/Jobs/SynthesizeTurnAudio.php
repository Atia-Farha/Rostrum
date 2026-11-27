<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Debate;
use App\Models\Turn;
use App\Services\EdgeTtsService;
use App\Services\Exceptions\TtsProviderException;
use App\Services\GeminiTtsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Synthesize TTS audio for a saved AI turn and persist the audio path.
 *
 * This job is chained from GenerateAiTurn and runs on the same queue.
 * It is kept separate so TTS failures do not prevent the transcript
 * from being saved and displayed — a debate can continue text-only
 * when both TTS providers are unavailable.
 */
final class SynthesizeTurnAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 15;

    public int $timeout = 60;

    public function __construct(
        private readonly Debate $debate,
        private readonly Turn $turn,
    ) {}

    public function handle(GeminiTtsService $geminiTts, EdgeTtsService $edgeTts): void
    {
        // Turn was already given audio — nothing to do.
        if ($this->turn->audio_path !== null) {
            return;
        }

        $audioFile = null;
        $audioProvider = null;

        try {
            $audioFile = $geminiTts->synthesize($this->turn->transcript, $this->debate->language);
            $audioProvider = 'gemini';
        } catch (TtsProviderException $exception) {
            Log::warning('SynthesizeTurnAudio: Gemini TTS failed', [
                'turn_id' => $this->turn->id,
                'error' => $exception->getMessage(),
            ]);

            if (! $exception->shouldFallback) {
                $this->recordAudioFailure();

                return;
            }
        } catch (\Throwable $exception) {
            Log::error('SynthesizeTurnAudio: Gemini TTS configuration failed', [
                'turn_id' => $this->turn->id,
                'error' => $exception->getMessage(),
            ]);

            $this->recordAudioFailure();

            return;
        }

        if ($audioFile === null) {
            try {
                $audioFile = $edgeTts->synthesize($this->turn->transcript, $this->debate->language);
                $audioProvider = 'edge_tts';
                Log::info('SynthesizeTurnAudio: Microsoft Edge TTS fallback used', ['turn_id' => $this->turn->id]);
            } catch (\Throwable $exception) {
                Log::warning('SynthesizeTurnAudio: Microsoft Edge TTS fallback failed', [
                    'turn_id' => $this->turn->id,
                    'error' => $exception->getMessage(),
                ]);

                $this->recordAudioFailure();

                // Audio is optional — the user receives a recoverable
                // text-only state rather than a failed debate turn.
                return;
            }
        }

        if ($audioFile !== null) {
            $this->turn->update([
                'audio_path' => $audioFile,
                'audio_provider' => $audioProvider,
                'audio_error' => null,
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('SynthesizeTurnAudio permanently failed', [
            'turn_id' => $this->turn->id,
            'error' => $e->getMessage(),
        ]);
        $this->recordAudioFailure();
    }

    private function recordAudioFailure(): void
    {
        $this->turn->update([
            'audio_error' => 'Audio is unavailable right now. You can continue reading the response.',
        ]);
    }
}
