<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Debate;
use App\Models\Turn;
use App\Services\ElevenLabsService;
use App\Services\GoogleTtsService;
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

    public int $timeout = 30;

    public function __construct(
        private readonly Debate $debate,
        private readonly Turn $turn,
    ) {}

    public function handle(ElevenLabsService $elevenlabs, GoogleTtsService $googleTts): void
    {
        // Turn was already given audio — nothing to do.
        if ($this->turn->audio_path !== null) {
            return;
        }

        $audioFile = null;

        try {
            $audioFile = $elevenlabs->synthesize(
                $this->turn->transcript,
                $this->debate->persona->voiceId($this->debate->language),
                $this->debate->language,
            );
        } catch (\Throwable $e) {
            Log::warning('SynthesizeTurnAudio: ElevenLabs failed — trying Google TTS', [
                'turn_id' => $this->turn->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($audioFile === null) {
            try {
                $audioFile = $googleTts->synthesize($this->turn->transcript, $this->debate->language);
                Log::info('SynthesizeTurnAudio: Google TTS fallback used', ['turn_id' => $this->turn->id]);
            } catch (\Throwable $e) {
                Log::warning('SynthesizeTurnAudio: Google TTS fallback failed', [
                    'turn_id' => $this->turn->id,
                    'error' => $e->getMessage(),
                ]);

                // Audio is optional — do not re-throw.
                return;
            }
        }

        if ($audioFile !== null) {
            $this->turn->update(['audio_path' => $audioFile]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('SynthesizeTurnAudio permanently failed', [
            'turn_id' => $this->turn->id,
            'error' => $e->getMessage(),
        ]);
        // TTS failure is non-fatal — debate continues without audio.
    }
}
