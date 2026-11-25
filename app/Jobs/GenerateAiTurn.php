<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Debate;
use App\Models\Round;
use App\Models\Turn;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Generate the AI opponent's debate turn response asynchronously.
 *
 * This job is dispatched immediately after the user's speech is saved.
 * On completion it dispatches SynthesizeTurnAudio so TTS begins as soon
 * as the text is ready. The front-end polls /debates/{debate}/state to
 * learn when both steps are done.
 */
final class GenerateAiTurn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 60;

    public function __construct(
        private readonly Debate $debate,
        private readonly Round $round,
        private readonly string $userTranscript,
        private readonly ?string $geminiAiText = null,
    ) {}

    public function handle(GeminiService $gemini): void
    {
        // If the AI already spoke in this round (ai_first flow), skip.
        if ($this->round->turns()->where('speaker', 'ai')->exists()) {
            return;
        }

        $aiMoveType = null;
        $aiText = null;

        if ($this->geminiAiText !== null && $this->geminiAiText !== '') {
            // Audio-fallback already produced the AI text — just extract the move tag.
            [$aiMoveType, $aiText] = $gemini->extractMoveType($this->geminiAiText);
        } else {
            try {
                $raw = $gemini->generateDebateTurn(
                    $this->debate,
                    $this->round,
                    $this->userTranscript,
                );
                [$aiMoveType, $aiText] = $gemini->extractMoveType($raw);
            } catch (\Throwable $e) {
                Log::error('GenerateAiTurn failed', [
                    'debate_id' => $this->debate->id,
                    'error' => $e->getMessage(),
                ]);
                // Re-throw so the queue retries / marks as failed.
                throw $e;
            }
        }

        if ($aiText === null || $aiText === '') {
            return;
        }

        $aiTurn = Turn::create([
            'id' => (string) Str::uuid(),
            'round_id' => $this->round->id,
            'speaker' => 'ai',
            'transcript' => $aiText,
            'audio_path' => null,
            'ai_move_type' => $aiMoveType,
        ]);

        // Chain TTS synthesis — it needs the saved turn ID for the audio URL.
        SynthesizeTurnAudio::dispatch($this->debate, $aiTurn);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateAiTurn permanently failed', [
            'debate_id' => $this->debate->id,
            'error' => $e->getMessage(),
        ]);
    }
}
