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
     * Receives audio blob + phase → transcribes, generates AI turn, synthesizes TTS.
     * Returns JSON for Alpine.js to swap into the page.
     */
    public function submitTurn(Request $request, Debate $debate): JsonResponse
    {
        $this->authorizeDebate($request, 'participate', $debate);

        $request->validate([
            'audio' => 'nullable|file',
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
                        'file'      => $userAudioName,
                    ]);

                    $userAudioUrl = null;
                } else {
                    $userAudioUrl = route('debates.audio', [
                        'debate' => $debate->id,
                        'file' => $userAudioName,
                    ]);
                }
            } catch (\Exception $e) {
                // Audio saving failed — continue without audio URL
                Log::error('Could not store the user recording', [
                    'debate_id' => $debate->id,
                    'error'     => $e->getMessage(),
                ]);

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
                    $sttError = null; // cleared — Gemini handled it
                } catch (\Exception $e) {
                    // Gemini audio fallback also failed — continue with null transcript
                    $geminiAiText = null;
                }
            }

            // --- 3. Save user turn (always — even with empty transcript or missing audio) ---
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

        // ── AI RESPONSE (independent of STT result) ─────────────────────────
        $aiText = null;
        $aiMoveType = null;
        $aiError = null;

        // If the AI already spoke first in this round (ai_first flow), the
        // user's turn just completed the exchange — do NOT generate a second AI speech.
        $aiAlreadySpoke = $currentRound->turns()
            ->where('speaker', 'ai')
            ->exists();

        // --- 4. Generate AI opponent response ---
        // If Gemini already produced a response via the audio fallback, skip a second call.
        if (! $aiAlreadySpoke) {
            if (isset($geminiAiText) && $geminiAiText) {
                // Extract the sparring move tag if present in the audio fallback response.
                [$aiMoveType, $aiText] = $this->gemini->extractMoveType($geminiAiText);
            } else {
                try {
                    // The speech the AI is answering. The turn has
                    // already been saved at this point, so the debate's
                    // own turns hold it — reading it from there rather
                    // than from the request means the AI answers the
                    // speech that is actually on the record.
                    $lastUserTranscript = $userTranscript
                        ?? $debate->allTurns()->where('speaker', 'user')->last()?->transcript
                        ?? '';

                    $aiText = $this->gemini->generateDebateTurn(
                        $debate,
                        $currentRound,
                        $lastUserTranscript,
                    );

                    // Extract the sparring move tag if present.
                    [$aiMoveType, $aiText] = $this->gemini->extractMoveType($aiText);
                } catch (\Exception $e) {
                    $aiText = null;
                    $aiError = $e->getMessage() ?: 'The opponent could not respond right now. Please try again.';
                }
            }
        }

        // ── TTS SYNTHESIS (independent of Gemini result) ─────────────────────
        $aiAudioFile = null;

        // --- 5. Synthesize AI voice (only if AI text exists, failure is non-fatal) ---
        if ($aiText) {
            try {
                $aiAudioFile = $this->elevenlabs->synthesize(
                    $aiText,
                    $debate->persona->voiceId($debate->language),
                    $debate->language
                );
            } catch (\Exception $e) {
                // TTS failure — fall back to Google Translate TTS (pure PHP, no API key)
                $aiAudioFile = null;
                Log::warning('ElevenLabs TTS failed — trying Google TTS', ['error' => $e->getMessage()]);
            }

            if ($aiAudioFile === null) {
                try {
                    $aiAudioFile = $this->googleTts->synthesize($aiText, $debate->language);
                    Log::info('TTS fallback used: Google Translate TTS');
                } catch (\Exception $e) {
                    Log::warning('Google TTS fallback failed', ['error' => $e->getMessage()]);
                    $aiAudioFile = null;
                }
            }
        }

        // --- 6. Save AI turn (only if we got AI text) ---
        $aiTurn = null;
        if ($aiText) {
            try {
                $aiTurn = Turn::create([
                    'id' => (string) Str::uuid(),
                    'round_id' => $currentRound->id,
                    'speaker' => 'ai',
                    'transcript' => $aiText,
                    'audio_path' => $aiAudioFile,
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
        $roundComplete = false;
        $debateComplete = false;
        $newRound = null;

        if ($debate->mode === 'tournament') {
            $roundComplete = $this->engine->isRoundComplete($currentRound->refresh());

            if ($roundComplete) {
                $newRound = $this->engine->advancePhase($debate);
                $debateComplete = ($newRound === null);

                if ($debateComplete) {
                    $debate->update(['status' => 'in_progress']);
                }
            }
        }

        // --- 8. Return all available data — partial results are always included ---
        return response()->json([
            // User turn data (always present even if STT failed)
            'user_transcript' => $userTranscript,
            'user_turn_id' => $userTurn?->id,
            'user_audio_url' => $userAudioUrl,
            'stt_error' => $sttError,   // null = no error
            'stt_provider' => $sttProvider,

            // AI turn data (present if Gemini succeeded)
            'ai_text' => $aiText,
            'ai_audio_url' => $aiAudioFile
                ? route('debates.audio', ['debate' => $debate->id, 'file' => $aiAudioFile])
                : null,
            'ai_move_type' => $aiMoveType,
            'ai_error' => $aiError,    // null = no error

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
     * Triggers adjudication after the final phase completes.
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

        $debate->update(['status' => 'adjudicated']);

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
            'debate'   => $debate,
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
