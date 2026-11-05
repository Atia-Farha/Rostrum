<?php

namespace Tests\Unit;

use App\Models\Debate;
use App\Models\Motion;
use App\Models\Persona;
use App\Models\Round;
use App\Services\DebateRoundEngine;
use App\Services\DebateStage;
use Database\Seeders\PersonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The stage brief is what makes the AI's speech fit the round.
 *
 * A phase holds two speeches, and the two speeches are not the same
 * speech. These tests hold the brief to that distinction: the same
 * phase, different slots, different jobs — and each speech pointed at
 * the speech it is supposed to be answering.
 */
class DebateStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PersonaSeeder::class);
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

    private function stage(): DebateStage
    {
        return new DebateStage(new DebateRoundEngine);
    }

    public function test_the_first_speaker_of_a_phase_builds_a_case(): void
    {
        // The user is Government, so the user opens the round and the
        // AI takes the second slot of the opening phase.
        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'opening', 1);

        $user = $this->stage()->brief($debate, $round, 'user', 'government');

        $this->assertSame('first', $user['slot']);
        $this->assertSame('opening.first', $user['slot_key']);
        $this->assertSame('Opening Speech', $user['role']);

        // The AI has not spoken, so it holds the second slot.
        $ai = $this->stage()->brief($debate, $round, 'ai', 'opposition');

        $this->assertSame('second', $ai['slot']);
        $this->assertSame('opening.second', $ai['slot_key']);
    }

    public function test_the_second_speaker_of_the_same_phase_answers_rather_than_builds(): void
    {
        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'opening', 1);

        // The user (Government) has spoken first. The AI (Opposition)
        // now takes the second slot of the same phase.
        $round->turns()->create([
            'id' => 'turn-user-1',
            'speaker' => 'user',
            'transcript' => 'This House believes the harms dominate, because attention is the commodity being sold.',
        ]);

        $brief = $this->stage()->brief($debate, $round, 'ai', 'opposition');

        $this->assertSame('second', $brief['slot']);
        $this->assertSame('opening.second', $brief['slot_key']);
        $this->assertSame('Reply to the Opening', $brief['role']);

        // The reply speech is a different job from the opening speech.
        $this->assertNotSame(
            config('debate.stage_profiles.opening.first.job'),
            $brief['job'],
        );

        // And it is pointed at the speech it is answering.
        $this->assertSame(
            'the opponent\'s opening speech',
            $brief['answering_label'],
        );
        $this->assertStringContainsString(
            'attention is the commodity',
            $brief['answering'],
        );
    }

    public function test_the_second_speaker_is_told_about_the_opponents_speech_to_answer(): void
    {
        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'opening', 1);

        $round->turns()->create([
            'id' => 'turn-user-1',
            'speaker' => 'user',
            'transcript' => 'The harms are systemic.',
        ]);

        $brief = $this->stage()->brief($debate, $round, 'ai', 'opposition');

        $this->assertNotNull($brief['answering']);
        $this->assertSame('The harms are systemic.', $brief['answering']);
    }

    public function test_a_speaker_with_nothing_to_answer_has_no_answering_speech(): void
    {
        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'opening', 1);

        $brief = $this->stage()->brief($debate, $round, 'ai', 'opposition');

        // The AI opens the round — there is nothing yet to answer.
        $this->assertNull($brief['answering']);
        $this->assertNull($brief['answering_label']);
    }

    public function test_the_second_speaker_of_the_rebuttal_answers_the_speech_just_given(): void
    {
        $debate = $this->makeDebate('government');

        $opening = $this->makeRound($debate, 'opening', 1);
        $opening->turns()->create([
            'id' => 'turn-user-1',
            'speaker' => 'user',
            'transcript' => 'The harms of social media are real but they are not decisive.',
        ]);

        $rebuttal = $this->makeRound($debate, 'rebuttal', 2);
        $rebuttal->turns()->create([
            'id' => 'turn-user-2',
            'speaker' => 'user',
            'transcript' => 'And the mechanism is attention capture.',
        ]);

        // The AI now speaks — it is in the second slot of the rebuttal
        // phase and answers the speech just given.
        $brief = $this->stage()->brief($debate, $rebuttal, 'ai', 'opposition');

        $this->assertSame('second', $brief['slot']);
        $this->assertSame('rebuttal.second', $brief['slot_key']);

        $this->assertSame(
            'the opponent\'s rebuttal speech',
            $brief['answering_label'],
        );
        $this->assertStringContainsString('attention capture', $brief['answering']);
    }

    public function test_the_first_speaker_of_a_phase_answers_the_previous_phases_speech(): void
    {
        // When the user is Opposition, the AI (Government) opens every
        // phase except the closing. The first speech of the rebuttal
        // phase therefore answers the user's reply from the phase
        // before.
        $debate = $this->makeDebate('opposition');

        $opening = $this->makeRound($debate, 'opening', 1);
        $opening->turns()->create([
            'id' => 'turn-ai-1',
            'speaker' => 'ai',
            'transcript' => 'The harms of social media are real but they are not decisive.',
        ]);
        $opening->turns()->create([
            'id' => 'turn-user-1',
            'speaker' => 'user',
            'transcript' => 'And the mechanism is attention capture.',
        ]);

        $rebuttal = $this->makeRound($debate, 'rebuttal', 2);

        $brief = $this->stage()->brief($debate, $rebuttal, 'ai', 'government');

        $this->assertSame('first', $brief['slot']);
        $this->assertSame('rebuttal.first', $brief['slot_key']);

        // It answers the speech from the opening phase — the user's
        // reply — and the label names the phase it was spoken in, not
        // the phase the AI is standing in now.
        $this->assertSame(
            'the opponent\'s reply speech',
            $brief['answering_label'],
        );
        $this->assertStringContainsString(
            'attention capture',
            $brief['answering'],
        );
    }

    public function test_a_speaker_with_no_opponent_speech_yet_has_nothing_to_answer(): void
    {
        // The AI opens the whole debate and the user has not spoken at
        // all, so there is nothing for the AI's rebuttal to answer.
        $debate = $this->makeDebate('opposition');

        $opening = $this->makeRound($debate, 'opening', 1);
        $opening->turns()->create([
            'id' => 'turn-ai-1',
            'speaker' => 'ai',
            'transcript' => 'My opening case.',
        ]);

        $rebuttal = $this->makeRound($debate, 'rebuttal', 2);

        $brief = $this->stage()->brief($debate, $rebuttal, 'ai', 'government');

        $this->assertNull($brief['answering']);
        $this->assertNull($brief['answering_label']);
    }

    public function test_the_closing_phase_reverses_who_opens_it(): void
    {
        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'closing', 3);

        // Government opened the debate, so the Opposition replies first.
        $brief = $this->stage()->brief($debate, $round, 'ai', 'opposition');

        $this->assertSame('first', $brief['slot']);
        $this->assertSame('closing.first', $brief['slot_key']);
        $this->assertSame('Closing Speech', $brief['role']);

        // The second speech in the closing phase is the final reply.
        $round->turns()->create([
            'id' => 'turn-ai-1',
            'speaker' => 'ai',
            'transcript' => 'The round comes down to whether the harms are decisive.',
        ]);

        $second = $this->stage()->brief($debate, $round, 'user', 'government');

        $this->assertSame('second', $second['slot']);
        $this->assertSame('closing.second', $second['slot_key']);
        $this->assertSame('Final Reply', $second['role']);
    }

    public function test_the_closing_speech_is_forbidden_from_new_arguments(): void
    {
        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'closing', 3);

        $brief = $this->stage()->brief($debate, $round, 'ai', 'opposition');

        $this->assertStringContainsString('no new arguments', strtolower($brief['job']));
    }

    public function test_the_brief_reports_which_phases_are_left(): void
    {
        $debate = $this->makeDebate('government');

        $this->makeRound($debate, 'opening', 1);
        $rebuttal = $this->makeRound($debate, 'rebuttal', 2);

        $brief = $this->stage()->brief($debate, $rebuttal, 'ai', 'opposition');

        $this->assertSame(['opening'], $brief['phases_done']);
        $this->assertSame(['closing'], $brief['phases_left']);
    }

    public function test_the_speaker_is_told_what_their_own_side_has_already_said(): void
    {
        $debate = $this->makeDebate('government');

        $opening = $this->makeRound($debate, 'opening', 1);
        $opening->turns()->create([
            'id' => 'turn-ai-1',
            'speaker' => 'ai',
            'transcript' => 'The harms are real but not decisive.',
        ]);

        $rebuttal = $this->makeRound($debate, 'rebuttal', 2);

        $brief = $this->stage()->brief($debate, $rebuttal, 'ai', 'opposition');

        $this->assertNotNull($brief['own_summary']);
        $this->assertStringContainsString('1 speech in the opening phase', $brief['own_summary']);
    }

    public function test_the_second_speaker_of_a_phase_is_given_a_shorter_budget_than_the_first(): void
    {
        $debate = $this->makeDebate('opposition');
        $round = $this->makeRound($debate, 'opening', 1);

        // The AI opens the round here (the user is Opposition).
        $first = $this->stage()->brief($debate, $round, 'ai', 'government');

        $round->turns()->create([
            'id' => 'turn-ai-1',
            'speaker' => 'ai',
            'transcript' => 'My opening case.',
        ]);

        $second = $this->stage()->brief($debate, $round, 'user', 'opposition');

        $this->assertNotSame($first['word_count'], $second['word_count']);
        $this->assertSame('150–220', $first['word_count']);
        $this->assertSame('110–170', $second['word_count']);
    }

    public function test_the_sparring_profile_is_used_for_a_sparring_round(): void
    {
        $debate = $this->makeDebate('government');
        $debate->update(['mode' => 'sparring']);

        $round = Round::create([
            'debate_id' => $debate->id,
            'phase' => null,
            'phase_order' => 1,
        ]);

        $brief = $this->stage()->brief($debate, $round, 'ai', 'opposition');

        $this->assertSame('sparring', $brief['slot_key']);
        $this->assertSame('Sparring Exchange', $brief['role']);
        $this->assertSame('100–160', $brief['word_count']);
    }

    public function test_the_brief_names_the_role_each_side_speaks_under(): void
    {
        $debate = $this->makeDebate('government');
        $round = $this->makeRound($debate, 'opening', 1);

        $ai = $this->stage()->brief($debate, $round, 'ai', 'opposition');
        $user = $this->stage()->brief($debate, $round, 'user', 'government');

        $this->assertSame('Leader of the Opposition', $ai['side_role']);
        $this->assertSame('Prime Minister', $user['side_role']);

        // The AI's opponent is the user, who is Government.
        $this->assertSame('Government', $ai['opponent']);
        $this->assertSame('the Prime Minister', $ai['opponent_first']);

        // The user's opponent is the AI, which is the Opposition.
        $this->assertSame('Opposition', $user['opponent']);
        $this->assertSame('the Leader of the Opposition', $user['opponent_first']);
    }
}
