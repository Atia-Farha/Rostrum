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

    private function makeDebate(string $userSide = 'government'): Debate
    {
        $motion = Motion::create([
            'text_en' => 'This House believes social media does more harm than good.',
            'text_bn' => null,
            'source' => 'manual',
        ]);

        return Debate::create([
            'session_id' => 'test-'.uniqid(),
            'motion_id' => $motion->id,
            'user_side' => $userSide,
            'persona_id' => Persona::first()->id,
            'difficulty' => 'intermediate',
            'mode' => 'tournament',
            'language' => 'en',
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

        // The AI is the Opposition, so it speaks as the Leader of the
        // Opposition and argues against the Prime Minister.
        $this->assertStringContainsString('Leader of the Opposition', $prompt);
        $this->assertStringContainsString('the Prime Minister', $prompt);
    }

    public function test_the_transcript_names_the_speakers_by_role(): void
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
        $this->assertStringContainsString('Prime Minister: The harms dominate.', $prompt);
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
