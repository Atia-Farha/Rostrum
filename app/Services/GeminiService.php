<?php

namespace App\Services;

use App\Models\Debate;
use App\Models\Round;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct(
        private DebateStage $stage,
    ) {
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
     *
     * The prompt is built from the stage brief (see DebateStage), which
     * knows not just which phase this is but which slot within it the
     * AI holds, which speech it is answering, and what the AI's own
     * side has already established. That is what makes the speech read
     * as a debater's turn in a round rather than as a reply to the
     * last message received.
     */
    public function generateDebateTurn(
        Debate $debate,
        Round  $round,
        string $userTranscript,
    ): string {
        $debate->load(['motion', 'persona']);

        $stage   = $this->stage->brief($debate, $round, 'ai', $debate->aiSide());
        $aiSide  = $debate->aiSide();
        $lang    = $this->languageName($debate->language);
        $motion  = $debate->motion->textFor($debate->language);
        $transcript = $debate->buildTranscript();

        $systemPrompt = $this->buildDebateSystemPrompt($debate);

        $userMessage = $this->buildDebateUserMessage(
            $stage,
            $motion,
            $transcript,
            $userTranscript,
            $lang,
        );

        return $this->generateTextWithSystem($systemPrompt, $userMessage);
    }

    /**
     * The system prompt for a debate turn.
     *
     * Four layers, in the order they matter:
     *
     *   1. The persona — who is speaking.
     *   2. The debate register — how a speech is delivered at a podium.
     *      Without this the model writes an essay, and the TTS voice
     *      then reads an essay aloud.
     *   3. The language — the instructions are in English even when the
     *      speech is not, and without saying so the model translates the
     *      instructions mid-sentence and the translation lands in the
     *      speech.
     *   4. The difficulty tier — how strong the speaker is.
     *
     * The persona comes first so the register is applied inside the
     * character's voice rather than flattening it.
     */
    private function buildDebateSystemPrompt(Debate $debate): string
    {
        $personaPrompt     = $debate->persona->system_prompt;
        $difficultyPrompt  = config('debate.difficulty_prompts', [])[$debate->difficulty] ?? '';
        $style             = config('debate.debate_style', []);

        $layers = array_filter([
            $personaPrompt,
            $style['register'] ?? '',
            implode("\n", array_map(
                fn (string $rule) => '- '.$rule,
                $style['rules'] ?? []
            )),
            $this->languageDirective($debate),
            $difficultyPrompt,
        ]);

        return implode("\n\n", $layers);
    }

    /**
     * The language the speech is written in.
     *
     * The instructions are always in English, but the speech is not
     * always in English. A model told to write a Bangla speech by
     * English instructions will, when it needs a label mid-sentence,
     * translate it — and the translation ends up spoken aloud as
     * something like "the esteemed Prime Minister". Saying plainly
     * that the instructions are English and only the speech is not
     * stops the instruction text leaking into the speech.
     */
    private function languageDirective(Debate $debate): string
    {
        if (($debate->language ?: 'en') === 'bn') {
            return 'These instructions are written in English, but the speech itself must be written in Bangla. '
                .'Keep the instruction text out of the speech: do not quote these instructions, do not translate a label '
                .'from English inside a Bangla sentence, and do not address anyone by an English title. '
                .'Use the Bangla side names exactly as they are given to you.';
        }

        return 'These instructions are written in English, and the speech must be written in English. '
            .'Keep the instruction text out of the speech.';
    }

    /**
     * The user message for a debate turn.
     *
     * @param  array<string, mixed>  $stage
     */
    private function buildDebateUserMessage(
        array  $stage,
        string $motion,
        string $transcript,
        string $userTranscript,
        string $lang,
    ): string {
        $sections = [];

        $sections[] = "MOTION: \"{$motion}\"";
        $sections[] = "YOU ARE: {$stage['side_role']}, arguing the {$stage['side']} side."
            ." The opponent is {$stage['opponent_first']}, arguing the {$stage['opponent']} side.";

        // The roles are for the model's own bookkeeping. The speech itself
        // refers to the SIDES, not to the offices — and the model has to be
        // told so, because it has just been handed two team names and a
        // transcript labelled with them, and left alone it opens by
        // addressing the other side by name. That is not how a speech at a
        // podium works, and in Bangla it comes out as a formal address
        // in the first line.
        $sections[] = "IN YOUR SPEECH, REFER TO THE SIDES, NOT TO THE SPEAKERS: "
            ."call your own side \"{$stage['side_self']}\" and the other side \"{$stage['opponent_in_speech']}\". "
            ."Do not name either speaker, and do not address anyone directly. "
            ."A speech answers the other side's ARGUMENT — \"their claim\", \"their evidence\" — "
            ."not \"the last speaker said\" or \"my opponent's point\".";

        // Where this speech sits in the round.
        $position = "THIS SPEECH: your {$stage['role']} — the {$stage['slot']} speech of the "
            ."{$stage['phase']} phase, which is {$stage['phase_purpose']}.";

        $sections[] = $position;

        // What this speaker gets to decide. The format gives the last
        // word to one speaker only, and the model has to be told which
        // one it is — in the same breath as the phase, before it starts
        // reasoning about what the phase is for.
        if (! empty($stage['authority']['note'])) {
            $sections[] = 'YOUR AUTHORITY IN THIS SPEECH: '.$stage['authority']['note'];
        }

        // What this speech has to do. This is the phase-aware,
        // slot-aware instruction — the thing that makes a reply a reply
        // rather than a second opening.
        $job = "YOUR JOB IN THIS SPEECH:\n".$stage['job'];

        $constraints = $stage['constraints'];
        if ($constraints !== []) {
            $job .= "\n\nRULES FOR THIS SPEECH:\n"
                .implode("\n", array_map(fn (string $c) => '- '.$c, $constraints));
        }

        $sections[] = $job;

        // The speech being answered. Without this the model has to guess
        // what it is responding to, and it guesses the last thing in
        // the transcript rather than the speech its slot is for.
        if (! empty($stage['answering'])) {
            $sections[] = "YOU ARE ANSWERING {$stage['answering_label']}.\n"
                ."Here it is, verbatim:\n\"{$stage['answering']}\"";
        }

        // The speaker's own position.
        if (! empty($stage['own_last'])) {
            $sections[] = "YOUR OWN LAST SPEECH ({$stage['own_last_label']}):\n"
                ."\"{$stage['own_last']}\"";
        }

        if (! empty($stage['own_summary'])) {
            $sections[] = $stage['own_summary'];
        }

        // The full round so far.
        if (trim($transcript) !== '') {
            $sections[] = "THE ROUND SO FAR:\n".$transcript;
        }

        // The speech that prompted this one.
        if (trim($userTranscript) !== '') {
            $sections[] = "THE SPEECH YOU HAVE JUST HEARD:\n\"{$userTranscript}\"";
        }

        // Where the round has got to, and what is left of it.
        $arc = $this->phaseArc($stage);
        if ($arc !== '') {
            $sections[] = $arc;
        }

        $sections[] = "DELIVER YOUR SPEECH NOW. {$stage['word_count']} words, in {$lang}. "
            ."Speak it — no headings, no bullet points, no markdown, no stage directions. "
            ."Output only the speech itself, nothing before or after it.";

        return implode("\n\n", $sections);
    }

    /**
     * Where the round has got to and what is left of it.
     *
     * This is what keeps a closing speech from introducing new
     * arguments and a reply from rebuilding the case — the model can
     * see that the opening is behind it and the weighing is coming.
     *
     * @param  array<string, mixed>  $stage
     */
    private function phaseArc(array $stage): string
    {
        $done = $stage['phases_done'] ?? [];
        $left = $stage['phases_left'] ?? [];

        if ($done === [] && $left === []) {
            return '';
        }

        $arc = [];

        if ($done !== []) {
            $arc[] = 'Phases already argued: '.implode(', ', $done).'.';
        }

        if ($left !== []) {
            $arc[] = 'Phases still to come after this speech: '.implode(', ', $left)
                .'. Do not spend material that belongs to a later phase — '
                .'in particular, do not introduce new arguments if the closing is still to come.';
        }

        return "WHERE THE ROUND STANDS:\n".implode("\n", $arc);
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
    public function rewriteArgument(string $transcript, Debate $debate, ?Round $round = null): array
    {
        $debate->load('motion');
        $motionText = $debate->motion->textFor($debate->language);
        $lang = $this->languageName($debate->language);

        // The speech being rewritten is the USER's, so the slot that
        // matters is the one the user held in that round. A rewrite that
        // knows only the phase will strengthen an opening speech the way
        // you strengthen a rebuttal, and vice versa.
        $stageInstruction = $this->rewriteStageInstruction($debate, $round);

        $prompt = <<<PROMPT
The following is one speech from a student practicing debate on the motion: "{$motionText}".
They are arguing the {$debate->user_side} side.

{$stageInstruction}

Rewrite it to be a meaningfully stronger version of the *same* speech — aligned with this specific debate stage, retaining the student's core position while elevating its quality.
Do not change what side they're arguing, and do not change what the speech is for. A reply stays a reply; an opening stays an opening.
Write it as it would be spoken at the podium: signposted, in full spoken sentences, with no headings, bullet points or markdown.
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

    /**
     * What a speech in this stage is for, for the rewrite prompt.
     *
     * It reuses the same stage profiles the debate turn uses, so the
     * coaching and the opponent are working from one description of
     * what each speech is meant to do. A rewrite that told the student
     * to build an opening case while the opponent was answering their
     * opening would teach them the wrong thing.
     */
    private function rewriteStageInstruction(Debate $debate, ?Round $round): string
    {
        $phase = $round?->phase ?? 'opening';

        if ($phase === 'sparring' || $round === null) {
            return 'This is a sparring exchange. Sharpen the structure, the evidence and the logic of the point being made, and make it land harder on the opponent. Keep it the same move and the same point.';
        }

        $slot = $this->stage->slotOf($debate, $round, 'user');
        $key  = "{$phase}.{$slot}";

        $profile = config('debate.stage_profiles', [])[$key] ?? null;

        if (! $profile) {
            return 'Focus the rewrite on sharper structure, better evidence or examples, and clearer logic.';
        }

        $instruction = "This is the student's {$profile['role']} — the {$slot} speech of the {$phase} phase. "
            ."A {$profile['role']} should: {$profile['job']} "
            .'Focus the rewrite on making this speech do its job more effectively.';

        $constraints = $profile['constraints'] ?? [];
        if ($constraints !== []) {
            $instruction .= "\nWhat this speech must respect:\n"
                .implode("\n", array_map(fn (string $c) => '- '.$c, $constraints));
        }

        return $instruction;
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

        // The rubric is written out from the shared config rather than
        // typed into the prompt. The report prints the same rubric back
        // to the reader, and a report that describes a rubric the judge
        // never scored against would be a document that misrepresents its
        // own method.
        $rubricText = $this->rubricPromptText();

        $prompt = <<<PROMPT
You are an experienced debate adjudicator scoring the user speaker on the motion: "{$motionText}".
The user argued the {$debate->user_side} side.

Scoring rubric:
{$rubricText}

Also identify any logical fallacies used by the user speaker (e.g. strawman, ad hominem, false dichotomy, slippery slope, appeal to emotion), citing the phase.

Give 3–5 concise, actionable feedback bullets. Ground every bullet in the user's ACTUAL words: quote the exact phrase or sentence from the transcript you are commenting on, then explain what to change. A bullet should look like: 'You said "X" — better to Y because Z.'

Name the phase each bullet refers to, so the reader knows where in the round to look. Write it as a capitalised phase name in the bullet text, for example "In the Opening, ..." or "Your Closing ...".

End the feedback with a final bullet that is a short practice plan: one specific, doable drill the user can run today (e.g. a 2-minute timed speaking drill on a specific weakness, or a specific argument structure to practise). Begin that final bullet with the tag [DRILL].

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
     * The rubric, written out as prompt lines.
     *
     * The criteria come from config('debate.rubric.criteria'), which is
     * the same source the report renders its scorecard from. Each line
     * carries the criterion's question — what the judge is looking at —
     * so the judge and the reader are working from one description.
     *
     * The bands go in too, for the same reason. If the judge is not told
     * where 27/40 on Matter stops being "Solid", the judge will invent
     * its own thresholds, and the report will then show a band the judge
     * did not apply. The judge and the report have to be working from
     * one rubric, or one of them is describing a round that did not
     * happen.
     */
    private function rubricPromptText(): string
    {
        $criteria = config('debate.rubric.criteria', []);
        $grades   = config('debate.rubric.grades', []);

        $lines = [];

        foreach ($criteria as $key => $criterion) {
            $lines[] = sprintf(
                '- %s (/%d): %s.',
                $criterion['label'] ?? ucfirst((string) $key),
                (int) ($criterion['weight'] ?? 0),
                $criterion['question'] ?? ''
            );

            foreach (($criterion['bands'] ?? []) as $band) {
                $lines[] = sprintf(
                    '    · %d+ %s — %s',
                    (int) ($band['min'] ?? 0),
                    $band['label'] ?? '',
                    $band['note'] ?? ''
                );
            }
        }

        // The overall bands, highest first so the ladder reads the way
        // the report shows it.
        $overall = [];

        foreach (collect($grades)->sortByDesc('min') as $grade) {
            $overall[] = sprintf(
                '%d+ %s — %s',
                (int) ($grade['min'] ?? 0),
                $grade['label'] ?? '',
                $grade['summary'] ?? ''
            );
        }

        if ($overall !== []) {
            $lines[] = '';
            $lines[] = 'Overall band (out of 100):';
            $lines[] = implode("\n", array_map(fn (string $line) => '    · ' . $line, $overall));
        }

        return implode("\n", $lines);
    }

    /**
     * Fallback: when ElevenLabs STT fails, send the raw audio file directly
     * to Gemini. Gemini will transcribe it AND generate the debate response
     * in one multimodal call.
     *
     * The response is built from the same stage brief as the text path,
     * so a debate that falls back to the audio route does not get a
     * weaker, phase-blind opponent. The one thing that changes is the
     * shape of the response: the transcript and the speech have to
     * come back together, so this call asks for JSON.
     *
     * Returns an array: ['transcript' => '...', 'ai_text' => '...']
     *
     * @throws \RuntimeException on API failure
     */
    public function generateDebateTurnFromAudio(
        Debate $debate,
        Round  $round,
        string $audioFilePath,
    ): array {
        $debate->load(['motion', 'persona']);

        $stage      = $this->stage->brief($debate, $round, 'ai', $debate->aiSide());
        $aiSide     = $debate->aiSide();
        $lang       = $this->languageName($debate->language);
        $motionText = $debate->motion->textFor($debate->language);
        $transcript = $debate->buildTranscript();

        $audioContent = base64_encode(file_get_contents($audioFilePath));
        $mimeType     = 'audio/webm';

        $prompt = $this->buildAudioTurnPrompt(
            $stage,
            $motionText,
            $transcript,
            $aiSide,
            $lang,
        );

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $this->buildDebateSystemPrompt($debate)]],
            ],
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

        $response = Http::timeout(90)
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
                throw new \RuntimeException($this->rateLimitMessage($response->body()));
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

    /**
     * The prompt for the multimodal audio fallback.
     *
     * It carries the same stage brief as the text path — the same
     * phase, the same slot, the same speech being answered — because
     * a fallback that produces a phase-blind speech is worse than no
     * fallback: the round keeps going with an opponent that has
     * stopped following the format.
     *
     * @param  array<string, mixed>  $stage
     */
    private function buildAudioTurnPrompt(
        array  $stage,
        string $motion,
        string $transcript,
        string $aiSide,
        string $lang,
    ): string {
        $sections = [];

        $sections[] = "MOTION: \"{$motion}\"";
        $sections[] = "YOU ARE: {$stage['side_role']}, arguing the {$aiSide} side. "
            ."The opponent is {$stage['opponent_first']}.";

        // The roles are for the model's own bookkeeping. The speech itself
        // refers to the SIDES, not to the speakers — see the same
        // instruction in the text-turn prompt.
        $sections[] = "IN YOUR SPEECH, REFER TO THE SIDES, NOT TO THE SPEAKERS: "
            ."call your own side \"{$stage['side_self']}\" and the other side \"{$stage['opponent_in_speech']}\". "
            ."Do not name either speaker, and do not address anyone directly. "
            ."A speech answers the other side's ARGUMENT — \"their claim\", \"their evidence\" — "
            ."not \"the last speaker said\" or \"my opponent's point\".";

        $sections[] = "THIS SPEECH: your {$stage['role']} — the {$stage['slot']} speech of the "
            ."{$stage['phase']} phase, which is {$stage['phase_purpose']}.";

        if (! empty($stage['authority']['note'])) {
            $sections[] = 'YOUR AUTHORITY IN THIS SPEECH: '.$stage['authority']['note'];
        }

        $job = "YOUR JOB IN THIS SPEECH:\n".$stage['job'];
        $constraints = $stage['constraints'];
        if ($constraints !== []) {
            $job .= "\n\nRULES FOR THIS SPEECH:\n"
                .implode("\n", array_map(fn (string $c) => '- '.$c, $constraints));
        }
        $sections[] = $job;

        if (! empty($stage['answering'])) {
            $sections[] = "YOU ARE ANSWERING {$stage['answering_label']}.\n"
                ."Here it is, verbatim:\n\"{$stage['answering']}\"";
        }

        if (! empty($stage['own_last'])) {
            $sections[] = "YOUR OWN LAST SPEECH ({$stage['own_last_label']}):\n"
                ."\"{$stage['own_last']}\"";
        }

        if (! empty($stage['own_summary'])) {
            $sections[] = $stage['own_summary'];
        }

        if (trim($transcript) !== '') {
            $sections[] = "THE ROUND SO FAR:\n".$transcript;
        }

        $arc = $this->phaseArc($stage);
        if ($arc !== '') {
            $sections[] = $arc;
        }

        $sections[] = "DELIVER YOUR SPEECH NOW. {$stage['word_count']} words, in {$lang}. "
            ."Speak it — no headings, no bullet points, no markdown, no stage directions.";

        $instructions = implode("\n\n", $sections);

        return <<<PROMPT
The following audio is from a student speaking in a debate on the motion: "{$motion}".
They are the opposing speaker, arguing the {$stage['opponent']} side.

First, transcribe exactly what the student said in the audio (their exact words, nothing else).
Then, as the opponent ({$aiSide} side), deliver your speech.

{$instructions}

Output ONLY valid JSON (no markdown fences, no preamble):
{"transcript": "exact words the student said", "ai_text": "your speech here"}
PROMPT;
    }

    /**
     * The name of a language, as the model should be asked to write it.
     */
    private function languageName(string $language): string
    {
        return $language === 'bn' ? 'Bangla' : 'English';
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

        $response = Http::timeout(60)
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
                throw new \RuntimeException($this->rateLimitMessage($response->body()));
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

        $response = Http::timeout(60)
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
                throw new \RuntimeException($this->rateLimitMessage($response->body()));
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
     * The message to show when Gemini answers 429.
     *
     * A 429 is not always the same thing. A transient per-minute rate limit
     * clears in seconds and "please wait a few seconds" is honest. A daily
     * free-tier quota does NOT clear in seconds — Google says so in the
     * response body, with the time until the quota resets — and telling the
     * reader to wait a few seconds for a quota that resets tomorrow is how a
     * broken app looks like a working one.
     *
     * The two are told apart by the upstream body: a quota exhaustion names
     * a daily limit ("per day", "per day limit exceeded", "free tier") and
     * usually carries a "retry in <duration>" hint.
     *
     * @param  string  $body  The raw response body from Gemini.
     */
    private function rateLimitMessage(string $body): string
    {
        $body = strtolower($body);

        $isDailyQuota = str_contains($body, 'per day')
            || str_contains($body, 'per day limit exceeded')
            || str_contains($body, 'free_tier_requests')
            || str_contains($body, 'free tier');

        if (! $isDailyQuota) {
            return 'The AI server is experiencing high traffic right now. Please wait a few seconds and try again.';
        }

        // Google states when the quota resets — "Please retry in
        // 17h18m8.03626646s". The seconds carry a fractional part, so
        // the pattern has to allow it or the whole match is lost.
        if (preg_match('/please retry in\s+([0-9]+h[0-9]+m[0-9]+(?:\.[0-9]+)?s)/i', $body, $m)) {
            // Drop the fractional part — "17h18m8.03626646s" reads as
            // noise in a message meant for a debater.
            $wait = preg_replace('/\.([0-9]+)s$/', 's', $m[1]);

            return "The AI's daily request limit has been reached, so it cannot speak right now. "
                ."The limit resets in about {$wait}.";
        }

        if (preg_match('/please retry in\s+([0-9]+h[0-9]+m)/i', $body, $m)) {
            $wait = $m[1];

            return "The AI's daily request limit has been reached, so it cannot speak right now. "
                ."The limit resets in about {$wait}.";
        }

        return "The AI's daily request limit has been reached, so it cannot speak right now. "
            ."Please try again later today.";
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

    /**
     * Strip the move tag off a sparring reply and name the move.
     *
     * In Sparring Mode the model opens its reply with a tag — [FOLLOW-UP],
     * [CONTRADICTION], [CHALLENGE] or [REBUTTAL] — which says which move it
     * has chosen. The tag is stripped before the speech is spoken, and the
     * move is stored on the turn so the reader can see what was done.
     *
     * The tag set is closed. The old parser matched any run of capitals in
     * square brackets, so a speech that opened with a bracketed aside had
     * that aside silently cut off the front of it. Only the four known
     * tags are recognised now; anything else stays in the speech where it
     * was written.
     *
     * @return array{0: ?string, 1: string} The move and the speech without it.
     */
    public function extractMoveType(string $text): array
    {
        $tags = config('debate.move_type_tags', []);

        if (preg_match('/^\s*\[([A-Z\-]+)\]/', $text, $matches)) {
            $tag = $matches[1];

            if (isset($tags[$tag])) {
                $remainder = trim(substr($text, strlen($matches[0])));

                return [$tags[$tag], $remainder];
            }
        }

        return [null, trim($text)];
    }
}
