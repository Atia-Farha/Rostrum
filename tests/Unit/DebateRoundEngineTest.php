<?php

namespace Tests\Unit;

use App\Models\Debate;
use App\Models\Motion;
use App\Models\Persona;
use App\Models\Round;
use App\Services\DebateRoundEngine;
use Database\Seeders\PersonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebateRoundEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PersonaSeeder::class);
    }

    private function makeDebate(string $userSide): Debate
    {
        $motion = Motion::create([
            'text_en' => 'This House believes social media does more harm than good.',
            'text_bn' => null,
            'source'  => 'manual',
        ]);

        return Debate::create([
            'session_id'  => 'test-' . uniqid(),
            'motion_id'   => $motion->id,
            'user_side'   => $userSide,
            'persona_id'  => Persona::first()->id,
            'difficulty'  => 'intermediate',
            'mode'        => 'tournament',
            'language'    => 'en',
            'status'      => 'in_progress',
        ]);
    }

    public function test_closing_phase_opposition_speaks_first_when_user_is_government(): void
    {
        $engine = new DebateRoundEngine();
        $debate = $this->makeDebate('government');

        $this->assertEquals('opposition', $engine->firstSpeakerForPhase($debate, 'closing'));

        $round = Round::create([
            'debate_id'   => $debate->id,
            'phase'       => 'closing',
            'phase_order' => 3,
        ]);

        $this->assertEquals('ai', $engine->nextSpeaker($debate, $round));
    }

    public function test_closing_phase_opposition_speaks_first_when_user_is_opposition(): void
    {
        $engine = new DebateRoundEngine();
        $debate = $this->makeDebate('opposition');

        $this->assertEquals('opposition', $engine->firstSpeakerForPhase($debate, 'closing'));

        $round = Round::create([
            'debate_id'   => $debate->id,
            'phase'       => 'closing',
            'phase_order' => 3,
        ]);

        $this->assertEquals('user', $engine->nextSpeaker($debate, $round));
    }

    public function test_opening_and_rebuttal_phases_government_speaks_first(): void
    {
        $engine = new DebateRoundEngine();
        $debate = $this->makeDebate('opposition');

        foreach (['opening', 'rebuttal'] as $phase) {
            $this->assertEquals('government', $engine->firstSpeakerForPhase($debate, $phase));
        }
    }

    public function test_round_with_two_turns_is_complete_even_when_ai_spoke_twice(): void
    {
        $engine = new DebateRoundEngine();
        $debate = $this->makeDebate('government');

        $round = Round::create([
            'debate_id'   => $debate->id,
            'phase'       => 'closing',
            'phase_order' => 3,
        ]);

        $round->turns()->createMany([
            ['id' => 'turn-ai-1', 'speaker' => 'ai',   'transcript' => 'Opposition first.'],
            ['id' => 'turn-us-1', 'speaker' => 'user', 'transcript' => 'User reply.'],
        ]);

        $this->assertTrue($engine->isRoundComplete($round));

        // A round where the AI spoke twice must still complete when the user has replied.
        $round2 = Round::create([
            'debate_id'   => $debate->id,
            'phase'       => 'closing',
            'phase_order' => 3,
        ]);

        $round2->turns()->createMany([
            ['id' => 'turn-ai-2', 'speaker' => 'ai',   'transcript' => 'Opposition first.'],
            ['id' => 'turn-us-2', 'speaker' => 'user', 'transcript' => 'User reply.'],
            ['id' => 'turn-ai-3', 'speaker' => 'ai',   'transcript' => 'Duplicate AI speech.'],
        ]);

        $this->assertTrue($engine->isRoundComplete($round2));
    }
}