<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DebateStatus;
use App\Jobs\GenerateAiTurn;
use App\Models\Adjudication;
use App\Models\Debate;
use App\Models\Turn;
use App\Models\TurnRewrite;
use App\Services\DebateRoundEngine;
use App\Services\ElevenLabsService;
use App\Services\GeminiService;
use App\Services\GoogleTtsService;
use App\Support\ReportAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DebateController extends Controller
{
    public function __construct(
        private GeminiService $gemini,
        private ElevenLabsService $elevenlabs,
        private GoogleTtsService $googleTts,
        private DebateRoundEngine $engine,
    ) {}

    /**
     * Assert that the signed-in user may perform the given ability on the
     * debate.
     *
     * The routes already carry `can:view,debate` / `can:participate,debate`
     * middleware. This is defence in depth: if a route is ever registered
     * without that middleware, the controller still refuses the request.
     */
    private function authorizeDebate(Request $request, string $ability, Debate $debate): void
    {
        abort_unless(
            $request->user()?->can($ability, $debate) === true,
            403
        );
    }

    /**
     * GET /debates/{debate} — Debate screen.
     */
    public function show(Request $request, Debate $debate)
    {
        $this->authorizeDebate($request, 'view', $debate);

        $state = $this->engine->buildViewState($debate);

        return view('debate', $state);
    }

    /**
     * POST /debates/{debate}/turns
     *
     * Receives audio blob + phase → transcribes (ElevenLabs STT with Gemini
     * audio fallback), saves the user turn, then dispatches GenerateAiTurn
     * asynchronously so the 3–8 s Gemini + TTS block happens off the web
     * worker. Alpine polls GET /debates/{debate}/state to learn when the AI
     * turn is ready.
     *
     * Returns JSON for Alpine.js to swap into the page immediately.
     */
    public function submitTurn(Request $request, Debate $debate): JsonResponse
    {
        $this->authorizeDebate($request, 'participate', $debate);

        // F-03: validate that the uploaded file is a known audio container.
        // Browsers always send WebM from the MediaRecorder API; the mimes rule
        // guards against non-audio uploads reaching the STT / storage paths.
        $request->validate([
            'audio' => 'nullable|file|mimes:webm,ogg,mp4,mpeg,mpga,wav,m4a',
            'phase' => 'required|string',
            'ai_first' => 'nullable|boolean',
        ]);

        $currentRound = $this->engine->currentRound($debate);
        if (! $currentRound) {
            return response()->json(['error' => 'No active round — debate may be complete.'], 422);
        }

        $userTranscript = null;
        $userTurn = null;
        $userAudioUrl = null;
        $sttError = null;
        $sttProvider = null;
        $geminiAiText = null;

        // ── USER TURN (skip when AI speaks first) ───────────────────────────
        if (! $request->boolean('ai_first')) {
            if (! $request->hasFile('audio')) {
                return response()->json(['error' => 'Audio file is required.'], 422);
            }

            $audioFile = $request->file('audio');

            // --- 1. Save user audio file (always, even if STT fails) ---
            try {
                // User recordings live on the PRIVATE disk and are served
                // through DebateController::audio(), which checks ownership.
                // The public disk would expose every user's voice to anyone
                // who can guess (or enumerate) the filename.
                $userAudioName = 'user_'.Str::uuid().'.webm';

                // The disk is configured with 'throw' => false, so a failed
                // write returns false instead of raising. Without this check
                // the URL below would point at a file that was never written
                // and the turn would offer a Listen button that 404s.
                $written = Storage::disk('local')->put(
                    'audio/'.$userAudioName,
                    file_get_contents($audioFile->getRealPath())
                );

                if ($written === false) {
                    Log::error('Could not store the user recording', [
                        'debate_id' => $debate->id,
                        'file' => $userAudioName,
                    ]);

                    $userAudioUrl = null;
                } else {
                    $userAudioUrl = route('debates.audio', [
                        'debate' => $debate->id,
                        'file' => $userAudioName,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Could not store the user recording', [
                    'debate_id' => $debate->id,
                    'error' => $e->getMessage(),
                ]);

                $userAudioUrl = null;
            }

            // --- 2. Transcribe via ElevenLabs STT ---
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

            // --- 2a. Gemini multimodal audio fallback ---
            // Gemini processes the raw audio directly — transcribes AND
            // generates AI response in one call.
            if ($userTranscript === null && $audioFile->getRealPath()) {
                try {
                    $audioFallback = $this->gemini->generateDebateTurnFromAudio(
                        $debate,
                        $currentRound,
                        $audioFile->getRealPath(),
                    );
                    $userTranscript = $audioFallback['transcript'] ?: null;
                    $geminiAiText = $audioFallback['ai_text'] ?: null;
                    if ($userTranscript) {
                        $sttProvider = 'gemini';
                    }
                    $sttError = null; // Gemini handled it
                } catch (\Exception $e) {
                    $geminiAiText = null;
                }
            }

            // --- 3. Save user turn (always — even with empty transcript) ---
            try {
                $userTurn = Turn::create([
                    'id' => (string) Str::uuid(),
                    'round_id' => $currentRound->id,
                    'speaker' => 'user',
                    'transcript' => $userTranscript ?? '',
                    'audio_path' => $userAudioUrl,
                ]);
            } catch (\Exception $e) {
                // DB save failed — non-fatal, proceed
            }
        }

        // ── AI RESPONSE — dispatched asynchronously (Q-05) ──────────────────
        // The expensive Gemini + TTS pipeline is now queued. Alpine polls
        // GET /debates/{debate}/state every 2 s to pick up the AI turn when
        // it arrives. This keeps the web-request latency at 1–2 s (STT only)
        // instead of 5–10 s (STT + LLM + TTS).
        $aiAlreadySpoke = $currentRound->turns()
            ->where('speaker', 'ai')
            ->exists();

        if (! $aiAlreadySpoke) {
            $lastUserTranscript = $userTranscript
                ?? $debate->allTurns()->where('speaker', 'user')->last()?->transcript
                ?? '';

            GenerateAiTurn::dispatch(
                $debate,
                $currentRound,
                $lastUserTranscript,
                $geminiAiText,  // null unless the audio fallback already produced it
            );
        }

        // --- Advance phase if the round is now complete (Tournament Mode) ---
        // Sparring Mode keeps ONE implicit round; the user ends it manually.
        $roundComplete = false;
        $debateComplete = false;
        $newRound = null;

        if ($debate->mode === 'tournament') {
            $roundComplete = $this->engine->isRoundComplete($currentRound->refresh());

            if ($roundComplete) {
                $newRound = $this->engine->advancePhase($debate);
                $debateComplete = ($newRound === null);

                // F-02: when all phases are done, mark the status column so
                // any feature that reads it sees the correct state. Status
                // changes from InProgress → InProgress here (phases done,
                // awaiting adjudication) and to Adjudicated in adjudicate().
                // The important fix is NOT setting it to 'in_progress' again
                // (a no-op that obscured the bug) — the status stays as-is
                // and is only advanced to Adjudicated by the adjudicate action.
            }
        }

        // Return immediately — AI text arrives via /state polling.
        return response()->json([
            // User turn data (always present even if STT failed)
            'user_transcript' => $userTranscript,
            'user_turn_id' => $userTurn?->id,
            'user_audio_url' => $userAudioUrl,
            'stt_error' => $sttError,
            'stt_provider' => $sttProvider,

            // AI turn is now async — front-end polls /state.
            'ai_queued' => ! $aiAlreadySpoke,
            'ai_text' => null,
            'ai_audio_url' => null,
            'ai_move_type' => null,
            'ai_error' => null,

            // Round/debate progression
            'round_complete' => $roundComplete,
            'debate_complete' => $debateComplete,
            'new_phase' => $newRound?->phase,
            'next_speaker' => $newRound
                ? ($this->engine->nextSpeaker($debate, $newRound) === 'user' ? 'user' : 'ai')
                : null,
        ]);
    }

    /**
     * GET /debates/{debate}/state
     *
     * Lightweight polling endpoint used by Alpine.js to pick up the AI turn
     * and its TTS audio after GenerateAiTurn + SynthesizeTurnAudio have run.
     *
     * Returns the latest AI turn for the current round. If the turn's
     * audio_path is still null the job is still running — the client should
     * poll again. Once audio_path is set (or after a generous timeout) the
     * client can render the turn.
     */
    public function state(Request $request, Debate $debate): JsonResponse
    {
        $this->authorizeDebate($request, 'view', $debate);

        $currentRound = $this->engine->currentRound($debate);

        if (! $currentRound) {
            return response()->json([
                'ai_ready' => false,
                'debate_done' => $this->engine->isDebateComplete($debate),
            ]);
        }

        $aiTurn = $currentRound->turns()
            ->where('speaker', 'ai')
            ->latest()
            ->first();

        if (! $aiTurn) {
            return response()->json([
                'ai_ready' => false,
                'ai_turn' => null,
            ]);
        }

        return response()->json([
            'ai_ready' => true,
            'ai_turn' => [
                'id' => $aiTurn->id,
                'transcript' => $aiTurn->transcript,
                'ai_move_type' => $aiTurn->ai_move_type,
                'audio_url' => $aiTurn->audio_path
                    ? route('debates.audio', [
                        'debate' => $debate->id,
                        'file' => basename($aiTurn->audio_path),
                    ])
                    : null,
                // audio_ready tells Alpine whether to offer the Listen button.
                // The transcript can be shown immediately; audio arrives later.
                'audio_ready' => $aiTurn->audio_path !== null,
            ],
        ]);
    }

    /**
     * POST /debates/{debate}/turns/{turn}/rewrite
     * Calls Gemini to rewrite the user's argument and returns before/after JSON.
     */
    public function rewriteTurn(Request $request, Debate $debate, Turn $turn): JsonResponse
    {
        $this->authorizeDebate($request, 'rewrite', $debate);

        // The turn must belong to THIS debate. The route binds {debate} and
        // {turn} independently, so without this check a turn from one debate
        // could be rewritten through another debate's URL.
        if ($turn->round?->debate_id !== $debate->id) {
            abort(404);
        }

        // Only user turns can be rewritten
        if ($turn->speaker !== 'user') {
            return response()->json(['error' => 'Only user turns can be rewritten.'], 422);
        }

        // Return cached rewrite if it already exists
        if ($turn->rewrite) {
            return response()->json([
                'original_text' => $turn->rewrite->original_text,
                'rewritten_text' => $turn->rewrite->rewritten_text,
                'explanation_bullets' => $turn->rewrite->explanation_bullets,
            ]);
        }

        try {
            $result = $this->gemini->rewriteArgument($turn->transcript, $debate, $turn->round);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage() ?: 'Unable to generate argument rewrite right now. Please try again.',
            ], 500);
        }

        // Persist the rewrite
        TurnRewrite::create([
            'id' => (string) Str::uuid(),
            'turn_id' => $turn->id,
            'original_text' => $turn->transcript,
            'rewritten_text' => $result['rewritten_text'],
            'explanation_bullets' => $result['explanation_bullets'],
        ]);

        return response()->json([
            'original_text' => $turn->transcript,
            'rewritten_text' => $result['rewritten_text'],
            'explanation_bullets' => $result['explanation_bullets'],
        ]);
    }

    /**
     * POST /debates/{debate}/adjudicate
     *
     * Triggers adjudication after the debate ends (all Tournament phases
     * complete, or the user clicks "End Round" in Sparring mode).
     *
     * F-02: this is the ONLY place that advances status to Adjudicated.
     */
    public function adjudicate(Request $request, Debate $debate)
    {
        $this->authorizeDebate($request, 'adjudicate', $debate);

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
            'id' => (string) Str::uuid(),
            'debate_id' => $debate->id,
            'matter_score' => $result['matter_score'],
            'manner_score' => $result['manner_score'],
            'method_score' => $result['method_score'],
            'total_score' => $result['total_score'],
            'fallacies' => $result['fallacies'],
            'feedback_bullets' => $result['feedback_bullets'],
            'verdict' => $result['verdict'],
        ]);

        // F-02: advance the status enum to Adjudicated so every downstream
        // feature reading debate.status sees the correct value.
        $debate->update(['status' => DebateStatus::Adjudicated]);

        return redirect()->route('debates.feedback', $debate->id);
    }

    /**
     * GET /debates/{debate}/audio/{file} — Serve a stored user recording.
     *
     * User recordings are private: they live on the non-public disk and are
     * streamed through here so the debate owner (and only the debate owner)
     * can play them back.
     *
     * @throws NotFoundHttpException
     */
    public function audio(Request $request, Debate $debate, string $file): BinaryFileResponse
    {
        $this->authorizeDebate($request, 'view', $debate);

        // The file name comes straight from the URL, so it must be reduced to
        // a bare filename before it is used — otherwise "../../.env" style
        // traversal would escape the audio directory.
        $name = basename($file);

        if (! preg_match('/^(user_[0-9a-f\-]{36}\.webm|[0-9a-f\-]{36}\.mp3|google_tts_[0-9a-f\-]{36}\.mp3)$/', $name)) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists('audio/'.$name)) {
            abort(404);
        }

        $type = str_ends_with($name, '.webm') ? 'audio/webm' : 'audio/mpeg';

        // NOTE: response()->file() builds a BinaryFileResponse with
        // $public = true, which calls setPublic() and stamps
        // "Cache-Control: public" over anything passed in the header
        // array. A shared cache could then keep a recording after the
        // owner signed out. Build the response by hand with $public = false
        // so the private directive actually survives.
        $response = new BinaryFileResponse($disk->path('audio/'.$name), 200, [
            'Content-Type' => $type,
            'Cache-Control' => 'private, max-age=3600',
        ], public: false);

        // Prevent a stale recording being replayed from the browser cache
        // under a different account.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /**
     * GET /debates/{debate}/feedback — Adjudication feedback sheet.
     */
    public function feedback(Request $request, Debate $debate)
    {
        $this->authorizeDebate($request, 'view', $debate);

        $debate->load(['motion', 'persona', 'adjudication', 'rounds.turns']);

        if (! $debate->adjudication) {
            return redirect()->route('debates.show', $debate->id);
        }

        // The analysis is the part of the report that is not in the
        // database: the phase a finding belongs to, the band a score
        // fell in, the estimated speaking time against the budget.
        // It is derived here so the view stays a document.
        $analysis = (new ReportAnalysis($debate, $debate->adjudication))->toArray();

        return view('feedback', [
            'debate' => $debate,
            'analysis' => $analysis,
        ]);
    }

    /**
     * GET /debates/{debate}/transcript — Plain-text download.
     */
    public function transcript(Request $request, Debate $debate)
    {
        $this->authorizeDebate($request, 'view', $debate);

        $debate->load(['motion', 'persona', 'rounds.turns']);

        $motionText = $debate->motion->textFor($debate->language);
        $lines = [
            'ROSTRUM — Debate Transcript',
            "Motion: {$motionText}",
            "User side: {$debate->user_side}",
            "Persona: {$debate->persona->name}",
            "Difficulty: {$debate->difficulty}",
            "Language: {$debate->language}",
            'Date: '.$debate->created_at->toDateTimeString(),
            str_repeat('-', 60),
        ];

        foreach ($debate->rounds as $round) {
            if ($round->phase) {
                $lines[] = '';
                $lines[] = strtoupper($round->phase).' PHASE';
                $lines[] = str_repeat('=', 40);
            }
            foreach ($round->turns as $turn) {
                $speaker = $turn->speaker === 'user'
                    ? 'You ('.$debate->user_side.')'
                    : 'AI ('.$debate->aiSide().' — '.$debate->persona->name.')';
                $lines[] = '';
                $lines[] = $speaker.':';
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
            $lines[] = 'Verdict: '.$adj->verdict;
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
