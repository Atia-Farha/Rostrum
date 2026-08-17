<?php

namespace App\Http\Controllers;

use App\Models\Adjudication;
use App\Models\Debate;
use App\Models\Turn;
use App\Models\TurnRewrite;
use App\Services\DebateRoundEngine;
use App\Services\ElevenLabsService;
use App\Services\GeminiService;
use App\Services\GoogleTtsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DebateController extends Controller
{
    public function __construct(
        private GeminiService     $gemini,
        private ElevenLabsService $elevenlabs,
        private GoogleTtsService  $googleTts,
        private DebateRoundEngine $engine,
    ) {}

    /**
     * GET /debates/{debate} — Debate screen.
     */
    public function show(Debate $debate)
    {
        $state = $this->engine->buildViewState($debate);
        return view('debate', $state);
    }

    /**
     * POST /debates/{debate}/turns
     * Receives audio blob + phase → transcribes, generates AI turn, synthesizes TTS.
     * Returns JSON for Alpine.js to swap into the page.
     */
    public function submitTurn(Request $request, Debate $debate): JsonResponse
    {
        $request->validate([
            'audio'    => 'nullable|file',
            'phase'    => 'required|string',
            'ai_first' => 'nullable|boolean',
        ]);

        $currentRound = $this->engine->currentRound($debate);
        if (! $currentRound) {
            return response()->json(['error' => 'No active round — debate may be complete.'], 422);
        }

        // The phase argument drives AI response length/strategy. Sparring Mode has
        // no phases — it uses its own 'sparring' word-count range and move-type prompt.
        $phaseArg = $debate->mode === 'sparring'
            ? 'sparring'
            : ($currentRound->phase ?? 'opening');

        $userTranscript   = null;
        $userTurn         = null;
        $userAudioUrl     = null;
        $sttError         = null;

        $sttProvider = null;

        // ── USER TURN (skip when AI speaks first) ───────────────────────────
        if (! $request->boolean('ai_first')) {
            if (! $request->hasFile('audio')) {
                return response()->json(['error' => 'Audio file is required.'], 422);
            }

            $audioFile = $request->file('audio');

            // --- 1. Save user audio file (always, even if STT fails) ---
            try {
                $userAudioFilename = 'audio/user_' . Str::uuid() . '.webm';
                \Illuminate\Support\Facades\Storage::disk('public')->put(
                    $userAudioFilename,
                    file_get_contents($audioFile->getRealPath())
                );
                // Relative URL — portable across hosts (see ElevenLabsService).
                $userAudioUrl = '/storage/' . $userAudioFilename;
            } catch (\Exception $e) {
                // Audio saving failed — continue without audio URL
                $userAudioUrl = null;
            }

            // --- 2. Transcribe via ElevenLabs STT (independent — failure triggers fallbacks) ---
            try {
                $userTranscript = $this->elevenlabs->transcribe(
                    $audioFile->getRealPath(),
                    $debate->language
                );

                if (strlen(trim($userTranscript ?? '')) < 10) {
                    $userTranscript = null;
                    $sttError = 'No clear speech was detected. Your recording was saved — try again or check your mic.';
                } else {
                    $sttProvider = 'elevenlabs';
                }
            } catch (\Exception $e) {
                $userTranscript = null;
                $sttError = $e->getMessage() ?: 'Could not transcribe your speech.';
            }

            // --- 2a. If STT still failed, use Gemini multimodal audio fallback ---
            // Gemini processes the raw audio directly — transcribes AND generates AI response in one call.
            if ($userTranscript === null && $audioFile->getRealPath()) {
                try {
                    $audioFallback  = $this->gemini->generateDebateTurnFromAudio(
                        $debate,
                        $audioFile->getRealPath(),
                        $phaseArg
                    );
                    $userTranscript = $audioFallback['transcript'] ?: null;
                    $geminiAiText   = $audioFallback['ai_text']    ?: null;
                    if ($userTranscript) {
                        $sttProvider = 'gemini';
                    }
                    $sttError       = null; // cleared — Gemini handled it
                } catch (\Exception $e) {
                    // Gemini audio fallback also failed — continue with null transcript
                    $geminiAiText = null;
                }
            }

            // --- 3. Save user turn (always — even with empty transcript or missing audio) ---
            try {
                $userTurn = Turn::create([
                    'id'         => (string) Str::uuid(),
                    'round_id'   => $currentRound->id,
                    'speaker'    => 'user',
                    'transcript' => $userTranscript ?? '',
                    'audio_path' => $userAudioUrl,
                ]);
            } catch (\Exception $e) {
                // DB save failed — non-fatal, proceed
            }
        }

        // ── AI RESPONSE (independent of STT result) ─────────────────────────
        $aiText      = null;
        $aiMoveType  = null;
        $aiError     = null;

        // --- 4. Generate AI opponent response ---
        // If Gemini already produced a response via the audio fallback, skip a second call.
        if (isset($geminiAiText) && $geminiAiText) {
            $aiText = $geminiAiText;
            // Extract sparring/tournament move type if present in audio fallback response
            if (preg_match('/^\[([A-Z\-\s]+)\]\s*/i', $aiText, $matches)) {
                $aiMoveType = strtolower(str_replace(['-', ' '], '_', trim($matches[1])));
                $aiText     = trim(preg_replace('/^\[[A-Z\-\s]+\]\s*/i', '', $aiText));
            }
        } else {
            try {
                $lastUserTranscript = $userTranscript
                    ?? $debate->allTurns()->where('speaker', 'user')->last()?->transcript
                    ?? '';

                $aiText = $this->gemini->generateDebateTurn(
                    $debate,
                    $lastUserTranscript,
                    $phaseArg
                );

                // Extract sparring/tournament move type if present
                if (preg_match('/^\[([A-Z\-\s]+)\]\s*/i', $aiText, $matches)) {
                    $aiMoveType = strtolower(str_replace(['-', ' '], '_', trim($matches[1])));
                    $aiText     = trim(preg_replace('/^\[[A-Z\-\s]+\]\s*/i', '', $aiText));
                }
            } catch (\Exception $e) {
                $aiText  = null;
                $aiError = $e->getMessage() ?: 'The opponent could not respond right now. Please try again.';
            }
        }

        // ── TTS SYNTHESIS (independent of Gemini result) ─────────────────────
        $aiAudioUrl = null;

        // --- 5. Synthesize AI voice (only if AI text exists, failure is non-fatal) ---
        if ($aiText) {
            try {
                $aiAudioUrl = $this->elevenlabs->synthesize(
                    $aiText,
                    $debate->persona->elevenlabs_voice_id,
                    $debate->language
                );
            } catch (\Exception $e) {
                // TTS failure — fall back to Google Translate TTS (pure PHP, no API key)
                $aiAudioUrl = null;
                Log::warning('ElevenLabs TTS failed — trying Google TTS', ['error' => $e->getMessage()]);
            }

            if ($aiAudioUrl === null) {
                try {
                    $aiAudioUrl = $this->googleTts->synthesize($aiText, $debate->language);
                    Log::info('TTS fallback used: Google Translate TTS');
                } catch (\Exception $e) {
                    Log::warning('Google TTS fallback failed', ['error' => $e->getMessage()]);
                    $aiAudioUrl = null;
                }
            }
        }

        // --- 6. Save AI turn (only if we got AI text) ---
        $aiTurn = null;
        if ($aiText) {
            try {
                $aiTurn = Turn::create([
                    'id'           => (string) Str::uuid(),
                    'round_id'     => $currentRound->id,
                    'speaker'      => 'ai',
                    'transcript'   => $aiText,
                    'audio_path'   => $aiAudioUrl,
                    'ai_move_type' => $aiMoveType,
                ]);
            } catch (\Exception $e) {
                // DB save failed — non-fatal
            }
        }

        // --- 7. Advance phase if the round is complete (Tournament Mode only) ---
        // Sparring Mode deliberately keeps ONE implicit round: the user ends the
        // debate manually with the "End Round" action, so no new rounds are ever
        // auto-created (and no tournament phase names leak into sparring).
        $roundComplete  = false;
        $debateComplete = false;
        $newRound       = null;

        if ($aiTurn && $debate->mode === 'tournament') {
            $roundComplete = $this->engine->isRoundComplete($currentRound->refresh());

            if ($roundComplete) {
                $newRound       = $this->engine->advancePhase($debate);
                $debateComplete = ($newRound === null);

                if ($debateComplete) {
                    $debate->update(['status' => 'in_progress']);
                }
            }
        }

        // --- 8. Return all available data — partial results are always included ---
        return response()->json([
            // User turn data (always present even if STT failed)
            'user_transcript'  => $userTranscript,
            'user_turn_id'     => $userTurn?->id,
            'user_audio_url'   => $userAudioUrl,
            'stt_error'        => $sttError,   // null = no error
            'stt_provider'     => $sttProvider,

            // AI turn data (present if Gemini succeeded)
            'ai_text'          => $aiText,
            'ai_audio_url'     => $aiAudioUrl,
            'ai_move_type'     => $aiMoveType,
            'ai_error'         => $aiError,    // null = no error

            // Round/debate progression
            'round_complete'   => $roundComplete,
            'debate_complete'  => $debateComplete,
            'new_phase'        => $newRound?->phase,
            'next_speaker'     => $newRound
                ? ($this->engine->nextSpeaker($debate, $newRound) === 'user' ? 'user' : 'ai')
                : null,
        ]);
    }


    /**
     * POST /debates/{debate}/turns/{turn}/rewrite
     * Calls Gemini to rewrite the user's argument and returns before/after JSON.
     */
    public function rewriteTurn(Debate $debate, Turn $turn): JsonResponse
    {
        // Only user turns can be rewritten
        if ($turn->speaker !== 'user') {
            return response()->json(['error' => 'Only user turns can be rewritten.'], 422);
        }

        // Return cached rewrite if it already exists
        if ($turn->rewrite) {
            return response()->json([
                'original_text'       => $turn->rewrite->original_text,
                'rewritten_text'      => $turn->rewrite->rewritten_text,
                'explanation_bullets' => $turn->rewrite->explanation_bullets,
            ]);
        }

        try {
            $phase  = $turn->round?->phase;
            $result = $this->gemini->rewriteArgument($turn->transcript, $debate, $phase);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage() ?: 'Unable to generate argument rewrite right now. Please try again.'
            ], 500);
        }

        // Persist the rewrite
        TurnRewrite::create([
            'id'                  => (string) Str::uuid(),
            'turn_id'             => $turn->id,
            'original_text'       => $turn->transcript,
            'rewritten_text'      => $result['rewritten_text'],
            'explanation_bullets' => $result['explanation_bullets'],
        ]);

        return response()->json([
            'original_text'       => $turn->transcript,
            'rewritten_text'      => $result['rewritten_text'],
            'explanation_bullets' => $result['explanation_bullets'],
        ]);
    }

    /**
     * POST /debates/{debate}/adjudicate
     * Triggers adjudication after the final phase completes.
     */
    public function adjudicate(Request $request, Debate $debate)
    {
        if ($debate->adjudication) {
            return redirect()->route('debates.feedback', $debate->id);
        }

        try {
            $result = $this->gemini->adjudicate($debate);
        } catch (\Exception $e) {
            $msg = $e->getMessage() ?: 'Adjudication generation took too long. Please try again.';
            return back()->with('error', $msg);
        }

        Adjudication::create([
            'id'               => (string) Str::uuid(),
            'debate_id'        => $debate->id,
            'matter_score'     => $result['matter_score'],
            'manner_score'     => $result['manner_score'],
            'method_score'     => $result['method_score'],
            'total_score'      => $result['total_score'],
            'fallacies'        => $result['fallacies'],
            'feedback_bullets' => $result['feedback_bullets'],
            'verdict'          => $result['verdict'],
        ]);

        $debate->update(['status' => 'adjudicated']);

        return redirect()->route('debates.feedback', $debate->id);
    }

    /**
     * GET /debates/{debate}/feedback — Adjudication feedback sheet.
     */
    public function feedback(Debate $debate)
    {
        $debate->load(['motion', 'persona', 'adjudication', 'rounds.turns']);

        if (! $debate->adjudication) {
            return redirect()->route('debates.show', $debate->id);
        }

        return view('feedback', ['debate' => $debate]);
    }

    /**
     * GET /debates/{debate}/transcript — Plain-text download.
     */
    public function transcript(Debate $debate)
    {
        $debate->load(['motion', 'persona', 'rounds.turns']);

        $motionText = $debate->motion->textFor($debate->language);
        $lines = [
            "ROSTRUM — Debate Transcript",
            "Motion: {$motionText}",
            "User side: {$debate->user_side}",
            "Persona: {$debate->persona->name}",
            "Difficulty: {$debate->difficulty}",
            "Language: {$debate->language}",
            "Date: " . $debate->created_at->toDateTimeString(),
            str_repeat('-', 60),
        ];

        foreach ($debate->rounds as $round) {
            if ($round->phase) {
                $lines[] = '';
                $lines[] = strtoupper($round->phase) . ' PHASE';
                $lines[] = str_repeat('=', 40);
            }
            foreach ($round->turns as $turn) {
                $speaker = $turn->speaker === 'user'
                    ? 'You (' . $debate->user_side . ')'
                    : 'AI (' . $debate->aiSide() . ' — ' . $debate->persona->name . ')';
                $lines[] = '';
                $lines[] = $speaker . ':';
                $lines[] = $turn->transcript;
            }
        }

        if ($debate->adjudication) {
            $adj = $debate->adjudication;
            $lines[] = '';
            $lines[] = str_repeat('-', 60);
            $lines[] = 'ADJUDICATION';
            $lines[] = "Matter: {$adj->matter_score}/40";
            $lines[] = "Manner: {$adj->manner_score}/30";
            $lines[] = "Method: {$adj->method_score}/30";
            $lines[] = "Total:  {$adj->total_score}/100";
            $lines[] = '';
            $lines[] = 'Verdict: ' . $adj->verdict;
            $lines[] = '';
            $lines[] = 'Feedback:';
            foreach ($adj->feedback_bullets as $bullet) {
                $lines[] = "• {$bullet}";
            }
        }

        $content = implode("\n", $lines);

        return response($content)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="rostrum-transcript.txt"');
    }
}
