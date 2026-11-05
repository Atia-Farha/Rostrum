<?php

namespace Tests\Unit;

use App\Models\Debate;
use App\Models\Motion;
use App\Models\Persona;
use App\Models\Round;
use App\Services\GeminiService;
use Database\Seeders\PersonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The prompt the AI actually receives.
 *
 * A debate prompt can be correct in every part and still produce a
 * speech that does not fit the round, so these tests read the request
 * that goes out to Gemini and hold it to what a speech in that slot
 * has to contain.
 */
class GeminiDebateTurnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PersonaSeeder::class);
    }

    /**
     * The prompt text sent to Gemini, from the last recorded request.
     */
    private function sentPrompt(): string
    {
        $request = Http::recorded()[0][0] ?? null;

        $this->assertNotNull($request, 'No request was sent to Gemini.');

        $payload = $request->data();

        $parts = $payload['contents'][0]['parts'] ?? [];

        $text = '';
        foreach ($parts as $part) {
            $text .= $part['text'] ?? '';
        }

        // The system instruction is a separate field, and it is where
        // the register rules live.
        $system = '';
        foreach (($payload['system_instruction']['parts'] ?? []) as $part) {
            $system .= $part['text'] ?? '';
        }

        return $system."\n\n".$text;
    }

    private function fakeGemini(string $reply = 'A speech.'): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => $reply]]]],
                ],
            ]),
        ]);
    }

    private function makeDebate(string $userSide = 'government', string $language = 'en'): Debate
    {
        $motion = Motion::create([
            'text_en' => 'This House believes social media does more harm than good.',
            'text_bn' => $language === 'bn' ? 'এই সভা মনে করে যে, সোশ্যাল মিডিয়া সম্পর্কে।' : null,
            'source' => 'manual',
        ]);

        return Debate::create([
            'session_id' => 'test-'.uniqid(),
            'motion_id' => $motion->id,
            'user_side' => $userSide,
            'persona_id' => Persona::first()->id,
            'difficulty' => 'intermediate',
            'mode' => 'tournament',
            'language' => $language,
            'status' => 'in_progress',
        ]);
    }

    private function makeRound(Debate $debate, string $phase, int $order): Round
    {
        return Round::create([
            'debate_id' => $debate->id,
            'phase' => $phase,
            'phase_order' => $order,
        ]);
    }

    public function test_the_prompt_names_the_speech_the_ai_is_giving(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('opposition');
        $round = $this->makeRound($debate, 'opening', 1);

        // The AI (Government) opens the round.
        resolve(GeminiService::class)->generateDebateTurn($debate, $round, '');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString('Opening Speech', $prompt);
        $this->assertStringContainsString('the first speech of the opening phase', $prompt);
    }

    public function test_the_reply_speech_is_told_to_answer_rather_than_rebuild(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'opening', 1);

        // The user (Government) has opened; the AI replies.
        $round->turns()->create([
            'id' => 'turn-user-1',
            'speaker' => 'user',
            'transcript' => 'The harms dominate because attention is the commodity sold.',
        ]);

        resolve(GeminiService::class)->generateDebateTurn($debate, $round, 'The harms dominate.');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString('Reply to the Opening', $prompt);
        $this->assertStringContainsString('the second speech of the opening phase', $prompt);

        // It is given the speech it is answering, and told to answer it.
        $this->assertStringContainsString('YOU ARE ANSWERING', $prompt);
        $this->assertStringContainsString('attention is the commodity', $prompt);

        // And it is not asked to build a case from the ground up.
        $this->assertStringNotContainsString('Build your case from the ground up', $prompt);
    }

    public function test_the_closing_speech_is_forbidden_from_new_arguments(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'closing', 3);

        resolve(GeminiService::class)->generateDebateTurn($debate, $round, 'My closing.');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString('Closing Speech', $prompt);
        $this->assertStringContainsString('no new arguments', strtolower($prompt));
    }

    public function test_the_prompt_carries_the_debate_register(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'opening', 1);

        resolve(GeminiService::class)->generateDebateTurn($debate, $round, 'My opening.');

        $prompt = $this->sentPrompt();

        // The register is what makes the output a speech rather than an
        // essay, and it is told not to use markdown.
        $this->assertStringContainsString('speaking at the podium', $prompt);
        $this->assertStringContainsString('No headings, no bullet points', $prompt);
        $this->assertStringContainsString('Signpost', $prompt);
    }

    /**
     * The prompt tells the model whether it decides the round.
     *
     * The first closing speaker kept announcing its own victory. The
     * cause was that the closing phase was described as the phase in
     * which a verdict is reached, and that description went to both
     * closing speeches. The prompt now states each slot's authority
     * as a fact of the slot.
     */
    public function test_the_prompt_states_who_decides_the_round(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'closing', 3);

        resolve(GeminiService::class)->generateDebateTurn($debate, $round, 'My rebuttal.');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString('YOUR AUTHORITY IN THIS SPEECH', $prompt);
        $this->assertStringContainsString('not the last speaker of the round', $prompt);
    }

    /**
     * The language of the speech is stated, because the instructions
     * are in English even when the speech is not.
     *
     * A model told to write a Bangla speech by English instructions
     * translates the instruction labels mid-sentence, and the
     * translation lands in the speech as a formal address.
     */
    public function test_the_prompt_says_the_instructions_are_english_and_the_speech_is_not(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('government', 'bn');
        $round = $this->makeRound($debate, 'opening', 1);

        resolve(GeminiService::class)->generateDebateTurn($debate, $round, 'আমাদের পক্ষের যুক্তি।');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString('These instructions are written in English', $prompt);
        $this->assertStringContainsString('the speech itself must be written in Bangla', $prompt);
        $this->assertStringContainsString('do not translate a label', $prompt);
    }

    /**
     * A speech does not announce the phase it is in.
     *
     * The register rules forbid pleasantries and throat-clearing, but
     * the model kept opening with a phase announcement — "Welcome to
     * the closing phase. Today we evaluated whether..." — because the
     * prompt tells it which phase it is standing in and it treats that
     * as something to say out loud. The ban has to name the failure
     * mode, because a generic "do not open with pleasantries" does not
     * cover it.
     */
    public function test_the_prompt_forbids_announcing_the_phase(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'closing', 3);

        resolve(GeminiService::class)->generateDebateTurn($debate, $round, 'My closing.');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString('Never announce the phase', $prompt);
        $this->assertStringContainsString('Welcome to the closing phase', $prompt);
    }

    /**
     * The Opposition's closing is a reply speech, not a verdict.
     *
     * In this format the Closing phase reverses: the Opposition speaks
     * first, as a reply to the Government's rebuttal, and the Government
     * takes the last word. The first closing profile used to tell the
     * speaker to announce which side won — which the Opposition, being
     * the first speaker, read as a speech congratulating itself.
     */
    public function test_the_opposition_closing_does_not_declare_a_winner(): void
    {
        $this->fakeGemini();

        // The user is Government, so the AI is the Opposition and holds
        // the first slot of the closing phase.
        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'closing', 3);

        resolve(GeminiService::class)->generateDebateTurn($debate, $round, 'My rebuttal.');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString('Closing Speech', $prompt);
        $this->assertStringContainsString('do not declare a winner', strtolower($prompt));
        $this->assertStringContainsString('The Government delivers the final word', $prompt);
    }

    /**
     * The Government's closing is the final word of the round.
     *
     * The closing phase reverses: the Opposition replies first, so the
     * Government speaks second and delivers the final word. The AI is
     * the Government when the user is the Opposition — and in that case
     * the AI holds the second slot of the closing phase.
     */
    public function test_the_government_closing_takes_the_last_word(): void
    {
        $this->fakeGemini();

        // The user is Opposition, so the AI is the Government. The
        // Opposition replies first in the closing phase, so the AI
        // speaks second — the final reply.
        $debate = $this->makeDebate('opposition');
        $round = $this->makeRound($debate, 'closing', 3);

        $round->turns()->create([
            'id' => 'turn-user-1',
            'speaker' => 'user',
            'transcript' => 'The clash is whether the harm is decisive.',
        ]);

        resolve(GeminiService::class)->generateDebateTurn($debate, $round, 'The clash is whether the harm is decisive.');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString('Final Reply', $prompt);
        $this->assertStringContainsString('the Government team', $prompt);
        $this->assertStringContainsString('last word of the round', $prompt);
    }

    public function test_the_prompt_carries_the_persona_and_the_difficulty(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'opening', 1);

        resolve(GeminiService::class)->generateDebateTurn($debate, $round, 'My opening.');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString($debate->persona->system_prompt, $prompt);
        $this->assertStringContainsString(
            config('debate.difficulty_prompts.intermediate'),
            $prompt,
        );
    }

    public function test_the_prompt_names_the_sides_by_their_roles(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'opening', 1);

        resolve(GeminiService::class)->generateDebateTurn($debate, $round, 'My opening.');

        $prompt = $this->sentPrompt();

        // The AI is the Opposition, so it speaks for the Opposition team
        // and argues against the Government team.
        $this->assertStringContainsString('the Opposition team', $prompt);
        $this->assertStringContainsString('the Government team', $prompt);
    }

    /**
     * No political office reaches the prompt.
     *
     * The sides used to be named by the offices they hold in parliament —
     * the Prime Minister and the Leader of the Opposition. That put a
     * political office into a debate competition, and it made the model
     * open its Bangla speeches by addressing the other side as "the
     * esteemed Prime Minister": a formal address no debater at a podium
     * uses. The sides are teams now, and the model must not be given a
     * title to put into the speech.
     */
    public function test_the_prompt_names_no_political_office(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'opening', 1);

        resolve(GeminiService::class)->generateDebateTurn($debate, $round, 'My opening.');

        $prompt = $this->sentPrompt();

        foreach (['Prime Minister', 'Leader of the Opposition', 'Opposition Leader'] as $office) {
            $this->assertStringNotContainsString(
                $office,
                $prompt,
                "The prompt still hands the model a political office: {$office}.",
            );
        }
    }

    /**
     * A Bangla debate is briefed in Bangla.
     *
     * A Bangla debate briefed in English forces the model to translate a
     * label mid-sentence, and the translation lands in the speech as an
     * address. The transcript the model reads is labelled in the language
     * it is writing in.
     */
    public function test_a_bangla_debate_is_briefed_in_bangla(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('government', 'bn');
        $round = $this->makeRound($debate, 'opening', 1);

        $round->turns()->create([
            'id' => 'turn-user-1',
            'speaker' => 'user',
            'transcript' => 'আমাদের পক্ষের যুক্তি।',
        ]);

        resolve(GeminiService::class)->generateDebateTurn($debate, $round, 'আমাদের পক্ষের যুক্তি।');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString('সরকার দল', $prompt);
        $this->assertStringContainsString('বিরোধী দল', $prompt);

        // The English labels must not leak into a Bangla debate.
        $this->assertStringNotContainsString('the Government team', $prompt);
        $this->assertStringNotContainsString('the Opposition team', $prompt);
    }

    /**
     * The model is told to argue with the sides, not the speakers.
     *
     * Without this the model takes the team names it has just been given
     * and puts them into the speech as a direct address.
     */
    public function test_the_prompt_tells_the_model_to_address_the_sides_not_the_speakers(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'opening', 1);

        resolve(GeminiService::class)->generateDebateTurn($debate, $round, 'My opening.');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString('REFER TO THE SIDES, NOT TO THE SPEAKERS', $prompt);
    }

    public function test_the_transcript_names_the_speakers_by_team(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'opening', 1);

        $round->turns()->create([
            'id' => 'turn-user-1',
            'speaker' => 'user',
            'transcript' => 'The harms dominate.',
        ]);

        resolve(GeminiService::class)->generateDebateTurn($debate, $round, 'The harms dominate.');

        $prompt = $this->sentPrompt();

        // Not "You" — the model was told it is writing a speech, so an
        // ambiguous label makes every turn a question of who is
        // speaking.
        $this->assertStringContainsString('the Government team: The harms dominate.', $prompt);
        $this->assertStringNotContainsString('You (government)', $prompt);
    }

    public function test_the_prompt_tells_the_ai_what_its_own_side_has_already_said(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('government');

        $opening = $this->makeRound($debate, 'opening', 1);
        $opening->turns()->create([
            'id' => 'turn-ai-1',
            'speaker' => 'ai',
            'transcript' => 'My opening case.',
        ]);

        $rebuttal = $this->makeRound($debate, 'rebuttal', 2);

        resolve(GeminiService::class)->generateDebateTurn($debate, $rebuttal, 'Your opening was weak.');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString('You have already given 1 speech in the opening phase', $prompt);
    }

    public function test_the_prompt_says_when_the_closing_is_still_to_come(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('government');

        $opening = $this->makeRound($debate, 'opening', 1);
        $rebuttal = $this->makeRound($debate, 'rebuttal', 2);

        resolve(GeminiService::class)->generateDebateTurn($debate, $rebuttal, 'My rebuttal.');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString('Phases still to come after this speech: closing', $prompt);
    }

    public function test_the_sparring_prompt_asks_for_a_move_type(): void
    {
        $this->fakeGemini();

        $debate = $this->makeDebate('government');
        $debate->update(['mode' => 'sparring']);

        $round = Round::create([
            'debate_id' => $debate->id,
            'phase' => null,
            'phase_order' => 1,
        ]);

        resolve(GeminiService::class)->generateDebateTurn($debate, $round, 'My point.');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString('Sparring Exchange', $prompt);
        $this->assertStringContainsString('[FOLLOW-UP]', $prompt);
    }

    public function test_a_known_move_tag_is_stripped_from_the_speech(): void
    {
        $service = resolve(GeminiService::class);

        [$move, $speech] = $service->extractMoveType('[FOLLOW-UP] You said the harms dominate. On what basis?');

        $this->assertSame('follow_up', $move);
        $this->assertSame('You said the harms dominate. On what basis?', $speech);
    }

    public function test_an_unknown_bracket_is_left_in_the_speech(): void
    {
        $service = resolve(GeminiService::class);

        [$move, $speech] = $service->extractMoveType('[Note] The harms dominate.');

        // The old parser swallowed any run of capitals in brackets, which
        // silently cut the front off a speech.
        $this->assertNull($move);
        $this->assertSame('[Note] The harms dominate.', $speech);
    }

    public function test_a_speech_without_a_tag_is_untouched(): void
    {
        $service = resolve(GeminiService::class);

        [$move, $speech] = $service->extractMoveType('The harms dominate.');

        $this->assertNull($move);
        $this->assertSame('The harms dominate.', $speech);
    }
}
