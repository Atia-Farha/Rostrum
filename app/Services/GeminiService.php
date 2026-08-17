<?php

namespace App\Services;

use App\Models\Debate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = (string) config('services.gemini.key', env('GEMINI_API_KEY', ''));
        $this->model   = (string) config('debate.gemini_model', 'gemini-2.0-flash');
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    }

    // -------------------------------------------------------------------------
    // A. Motion Generation
    // -------------------------------------------------------------------------

    /**
     * Generate a debatable motion using Gemini.
     */
    public function generateMotion(string $language, ?string $category = null): string
    {
        $categoryPhrase = $category
            ? "in the style of '{$category}'"
            : 'on any contemporary topic';

        $phraseFormat = $language === 'bn'
            ? "Phrase it appropriately for a Bangla debate (e.g. 'এই সভা মনে করে যে...' or 'এই সভা...')."
            : "Phrase it as 'This House believes...'.";

        $langTarget = $language === 'bn'
            ? 'Bangla'
            : 'English';

        $prompt = "Generate one debatable, balanced motion {$categoryPhrase} for a university debate. "
            . "{$phraseFormat} "
            . "Respond in {$langTarget}. "
            . "Output only the motion, no preamble, no explanation.";

        return $this->generateText($prompt);
    }

    // -------------------------------------------------------------------------
    // B. Debate Turn (Opponent Response)
    // -------------------------------------------------------------------------

    /**
     * Generate the AI opponent's debate turn response.
     * Accepts the user's transcript text (already transcribed from audio).
     */
    public function generateDebateTurn(
        Debate $debate,
        string $userTranscript,
        string $currentPhase
    ): string {
        $debate->load(['motion', 'persona']);

        $personaPrompt     = $debate->persona->system_prompt;
        $difficultyPrompts = config('debate.difficulty_prompts', []);
        $difficultyPrompt  = $difficultyPrompts[$debate->difficulty] ?? '';
        $wordCounts        = config('debate.ai_word_count', []);
        $wordCount         = $wordCounts[$currentPhase] ?? '100–160';
        $lang = $debate->language === 'bn'
            ? 'Bangla'
            : 'English';
        $motionText       = $debate->motion->textFor($debate->language);
        $aiSide           = $debate->aiSide();
        $priorTranscript  = $debate->buildTranscript();

        $phaseInstruction = $this->buildPhaseInstruction($debate, $currentPhase);

        $systemPrompt = implode("\n\n", array_filter([
            $personaPrompt,
            $difficultyPrompt,
        ]));

        $userMessage = <<<PROMPT
Motion: "{$motionText}"
You are arguing the {$aiSide} side.

{$phaseInstruction}

Full debate transcript so far:
{$priorTranscript}

User's most recent speech:
{$userTranscript}

Respond as your persona would, at your assigned skill level — in spoken debate register, {$wordCount} words, in {$lang}.
Directly engage with the user's most recent point where relevant.
Do not break character or mention you are an AI. Do not use markdown formatting.
PROMPT;

        return $this->generateTextWithSystem($systemPrompt, $userMessage);
    }

    // -------------------------------------------------------------------------
    // C. Argument Rewrite (Before/After Coaching)
    // -------------------------------------------------------------------------

    /**
     * Rewrite a user's argument to be stronger, returning the rewritten text
     * and 2–3 explanation bullets describing what changed.
     *
     * @return array{rewritten_text: string, explanation_bullets: string[]}
     */
    public function rewriteArgument(string $transcript, Debate $debate, ?string $phase = null): array
    {
        $debate->load('motion');
        $motionText = $debate->motion->textFor($debate->language);
        $lang = $debate->language === 'bn'
            ? 'Bangla'
            : 'English';
        $motionText = $debate->motion->textFor($debate->language);

        $stageInstruction = match ($phase) {
            'opening'  => 'This speech is in the OPENING phase. It should establish a strong, principled framework, define the motion\'s key terms in the speaker\'s favor, explain clear mechanisms, and introduce fully developed constructive arguments. Focus the rewrite on building that case with maximum clarity and impact.',
            'rebuttal' => 'This speech is in the REBUTTAL phase. It must engage the opponent\'s case directly: dismantle the strongest opposing point, expose unproven premises or logical gaps, and reinforce the speaker\'s own core case. Focus the rewrite on sharper, more surgical clash — target the opponent\'s weakest link.',
            'closing'  => 'This speech is in the CLOSING phase — the final reply speech. It should weigh the debate: crystallize the core clash, compare the strength of each side\'s arguments and impacts, settle any outstanding opposition points, and end with a persuasive summary of why the speaker\'s side has won. Focus the rewrite on impact comparison and persuasive framing.',
            default    => 'Focus the rewrite on sharper structure, better evidence or examples, and clearer logic.',
        };

        $prompt = <<<PROMPT
The following is one turn from a student practicing debate on the motion: "{$motionText}".
They are arguing the {$debate->user_side} side.

{$stageInstruction}

Rewrite it to be a meaningfully stronger version of the *same* argument — aligned with this specific debate stage, retaining the user's core position while elevating its quality.
Do not change what side they're arguing.
Respond in {$lang}.

Output ONLY valid JSON matching this schema (no markdown fences, no preamble):
{"rewritten_text": "string", "explanation_bullets": ["string", "string"]}

explanation_bullets should be 2–3 short phrases naming what changed (e.g. "strengthened opening framework", "sharpened clash weighing").

Original argument:
{$transcript}
PROMPT;

        $raw  = $this->generateText($prompt);
        $data = $this->parseJson($raw);

        return [
            'rewritten_text'       => $data['rewritten_text']       ?? $raw,
            'explanation_bullets'  => $data['explanation_bullets']  ?? [],
        ];
    }

    // -------------------------------------------------------------------------
    // D. Adjudication
    // -------------------------------------------------------------------------

    /**
     * Score a completed debate round and return structured adjudication data.
     *
     * @return array{matter_score: int, manner_score: int, method_score: int, total_score: int, fallacies: array, feedback_bullets: array, verdict: string}
     */
    public function adjudicate(Debate $debate): array
    {
        $debate->load(['motion', 'persona']);
        $motionText      = $debate->motion->textFor($debate->language);
        $lang = match ($debate->language) {
            'bn'    => 'Bangla',
            default => 'English',
        };
        $fullTranscript  = $debate->buildTranscript();

        $prompt = <<<PROMPT
You are an experienced debate adjudicator scoring the user speaker on the motion: "{$motionText}".
The user argued the {$debate->user_side} side.

Scoring rubric:
- Matter (/40): Quality, relevance, and logical soundness of arguments and evidence.
- Manner (/30): Persuasiveness, clarity, and rhetorical delivery (infer from language in the transcript).
- Method (/30): Structure, organization, and how directly the speaker rebutted the opponent.

Also identify any logical fallacies used by the user speaker (e.g. strawman, ad hominem, false dichotomy, slippery slope, appeal to emotion), citing the phase.

Give 3–5 concise, actionable feedback bullets. Ground every bullet in the user's ACTUAL words: quote the exact phrase or sentence from the transcript you are commenting on, then explain what to change. A bullet should look like: 'You said "X" — better to Y because Z.'

End the feedback with a final bullet that is a short practice plan: one specific, doable drill the user can run today (e.g. a 2-minute timed speaking drill on a specific weakness, or a specific argument structure to practise).

Give a one-line verdict.

Respond in {$lang}.

Full debate transcript:
{$fullTranscript}

Output ONLY valid JSON matching this schema (no markdown fences, no preamble):
{
  "matter_score": integer,
  "manner_score": integer,
  "method_score": integer,
  "total_score": integer,
  "fallacies": [{"phase": "string", "type": "string", "explanation": "string"}],
  "feedback_bullets": ["string"],
  "verdict": "string"
}
PROMPT;

        $raw  = $this->generateText($prompt);
        $data = $this->parseJson($raw);

        return [
            'matter_score'     => (int) ($data['matter_score']     ?? 0),
            'manner_score'     => (int) ($data['manner_score']     ?? 0),
            'method_score'     => (int) ($data['method_score']     ?? 0),
            'total_score'      => (int) ($data['total_score']      ?? 0),
            'fallacies'        => $data['fallacies']               ?? [],
            'feedback_bullets' => $data['feedback_bullets']        ?? [],
            'verdict'          => $data['verdict']                 ?? '',
        ];
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Fallback: when ElevenLabs STT fails, send the raw audio file directly
     * to Gemini. Gemini will transcribe it AND generate the debate response
     * in one multimodal call.
     *
     * Returns an array: ['transcript' => '...', 'ai_text' => '...']
     *
     * @throws \RuntimeException on API failure
     */
    public function generateDebateTurnFromAudio(
        Debate  $debate,
        string  $audioFilePath,
        string  $currentPhase
    ): array {
        $debate->load(['motion', 'persona']);

        $personaPrompt    = $debate->persona->system_prompt;
        $difficultyPrompt = config('debate.difficulty_prompts', [])[$debate->difficulty] ?? '';
        $wordCount        = config('debate.ai_word_count', [])[$currentPhase] ?? '100–160';
        $lang = $debate->language === 'bn'
            ? 'Bangla'
            : 'English';
        $motionText      = $debate->motion->textFor($debate->language);
        $aiSide          = $debate->aiSide();
        $priorTranscript = $debate->buildTranscript();
        $phaseInstruction = $this->buildPhaseInstruction($debate, $currentPhase);

        $audioContent = base64_encode(file_get_contents($audioFilePath));
        $mimeType     = 'audio/webm';

        $prompt = <<<PROMPT
The following audio is from a student speaking in a debate on the motion: "{$motionText}".
They are arguing the {$debate->user_side} side.

First, transcribe exactly what the student said in the audio (their exact words, nothing else).
Then, as the opponent ({$aiSide} side), generate your debate response.

Debate context so far:
{$priorTranscript}

{$personaPrompt}
{$difficultyPrompt}
{$phaseInstruction}

Respond in {$lang} in approximately {$wordCount} words.

Output ONLY valid JSON (no markdown fences, no preamble):
{"transcript": "exact words the student said", "ai_text": "your opponent response here"}
PROMPT;

        $payload = [
            'contents' => [[
                'parts' => [
                    [
                        'inlineData' => [
                            'mimeType' => $mimeType,
                            'data'     => $audioContent,
                        ],
                    ],
                    ['text' => $prompt],
                ],
            ]],
            'generationConfig' => ['temperature' => 0.7],
        ];

        $response = Http::withoutVerifying()
            ->timeout(90)
            ->retry(3, 3000, function (\Exception $exception) {
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    return in_array($exception->response->status(), [429, 500, 502, 503, 504]);
                }
                return true;
            }, throw: false)
            ->post(
                "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
                $payload
            );

        if ($response->failed()) {
            $status = $response->status();
            Log::error('Gemini audio turn failed', ['status' => $status, 'body' => $response->body()]);
            if ($status === 429) {
                throw new \RuntimeException('The AI server is experiencing high traffic right now. Please wait a few seconds and try again.');
            }
            throw new \RuntimeException('Unable to process the audio with the AI service. Please try again.');
        }

        $raw  = $this->extractText($response->json());
        $data = $this->parseJson($raw);

        return [
            'transcript' => trim($data['transcript'] ?? ''),
            'ai_text'    => trim($data['ai_text']    ?? $raw),
        ];
    }

    private function generateText(string $prompt): string
    {
        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
            ],
        ];

        $response = Http::withoutVerifying()
            ->timeout(60)
            ->retry(5, 3000, function (\Exception $exception, $request) {
                // Exponential sleep / retry on rate limit (429) or temporary server errors (5xx)
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    return in_array($exception->response->status(), [429, 500, 502, 503, 504]);
                }
                return true;
            }, throw: false)
            ->post(
                "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
                $payload
            );

        if ($response->failed()) {
            Log::error('Gemini generateText failed', ['status' => $response->status(), 'body' => $response->body()]);
            $status = $response->status();
            if ($status === 429) {
                throw new \RuntimeException('The AI server is experiencing high traffic right now. Please wait a few seconds and try again.');
            }
            throw new \RuntimeException('Unable to connect to the AI service. Please try again in a moment.');
        }

        return $this->extractText($response->json());
    }

    private function generateTextWithSystem(string $systemPrompt, string $userMessage): string
    {
        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userMessage]]],
            ],
            'generationConfig' => [
                'temperature' => 0.85,
            ],
        ];

        $response = Http::withoutVerifying()
            ->timeout(60)
            ->retry(5, 3000, function (\Exception $exception, $request) {
                // Exponential sleep / retry on rate limit (429) or temporary server errors (5xx)
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    return in_array($exception->response->status(), [429, 500, 502, 503, 504]);
                }
                return true;
            }, throw: false)
            ->post(
                "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}",
                $payload
            );

        if ($response->failed()) {
            Log::error('Gemini generateTextWithSystem failed', ['status' => $response->status(), 'body' => $response->body()]);
            $status = $response->status();
            if ($status === 429) {
                throw new \RuntimeException('The AI server is experiencing high traffic right now. Please wait a few seconds and try again.');
            }
            throw new \RuntimeException('Unable to connect to the AI service. Please try again in a moment.');
        }

        return $this->extractText($response->json());
    }

    private function extractText(array $response): string
    {
        return $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    /**
     * Parse a JSON string returned by Gemini, stripping any markdown fences.
     */
    private function parseJson(string $raw): array
    {
        // Strip markdown code fences (```json ... ``` or ``` ... ```)
        $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $cleaned = preg_replace('/\s*```$/m', '', $cleaned ?? $raw);
        $cleaned = trim($cleaned ?? $raw);

        $decoded = json_decode($cleaned, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Gemini JSON parse failed, raw output: ' . $raw);
            return [];
        }
        return $decoded;
    }

    private function buildPhaseInstruction(Debate $debate, string $currentPhase): string
    {
        if ($debate->mode === 'sparring') {
            return 'This is a Sparring Mode debate. After reviewing the full transcript, choose the most appropriate move type: '
                . 'a follow-up question probing an unsupported claim, a contradiction callout if the user contradicts themselves, '
                . 'a live challenge ("give me one piece of evidence for that"), or a standard rebuttal. '
                . 'Begin your response by stating which move type you are using (e.g. [FOLLOW-UP] or [REBUTTAL]).';
        }

        return match ($currentPhase) {
            'opening'  => 'This is the OPENING phase. You are delivering a constructive opening speech: establish the framework and principles your side stands on, define or scope the motion in your favor if needed, and present 2–3 fully developed constructive arguments with reasoning and concrete examples. Build your case from the ground up — do not reply to the opposition yet.',
            'rebuttal' => 'This is the REBUTTAL phase. You are a second speaker: directly engage with the main points the opposing speaker just made, point-by-point. Identify and attack their weakest premise or clearest logical gap, and reinforce your own core case in the process. Do not merely repeat your opening speech.',
            'closing'  => 'This is the CLOSING phase — the final reply speech of the debate. Do not introduce new arguments. Weigh the debate: crystallize the core clash, compare whose arguments survive scrutiny, address any outstanding opposition points, and deliver a powerful closing statement of why your side has won the round.',
            default    => 'Respond as appropriate for the current phase of the debate.',
        };
    }
}
