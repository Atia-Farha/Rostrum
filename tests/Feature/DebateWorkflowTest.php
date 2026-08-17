<?php

namespace Tests\Feature;

use App\Models\Persona;
use Database\Seeders\PersonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PersonaSeeder::class);
    }

    public function test_home_page_loads(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Rostrum');
        $response->assertSee('AI Debate Training Platform');
    }

    public function test_setup_screen_renders_with_personas(): void
    {
        $response = $this->get('/setup');
        $response->assertStatus(200);
        $response->assertSee('Calm Logician');
        $response->assertSee('Aggressive Cross-Examiner');
        $response->assertSee('Devil', false);
    }

    public function test_debate_creation_stores_models_and_seeds_first_round(): void
    {
        $persona = Persona::first();

        $response = $this->post('/debates', [
            'motion_text' => 'This House believes social media does more harm than good.',
            'user_side'   => 'government',
            'persona_id'  => $persona->id,
            'difficulty'  => 'intermediate',
            'mode'        => 'tournament',
            'language'    => 'en',
        ]);

        $this->assertDatabaseHas('motions', [
            'text_en' => 'This House believes social media does more harm than good.',
        ]);

        $debate = \App\Models\Debate::first();
        $this->assertNotNull($debate);
        $this->assertEquals('government', $debate->user_side);
        $this->assertEquals('intermediate', $debate->difficulty);

        $response->assertRedirect('/debates/' . $debate->id);

        $debateResponse = $this->get('/debates/' . $debate->id);
        $debateResponse->assertStatus(200);
        $debateResponse->assertSee('Opening');
    }

    public function test_sparring_debate_renders_end_round_without_phases(): void
    {
        $persona = Persona::first();

        $response = $this->post('/debates', [
            'motion_text' => 'This House would legalize ride-sharing apps.',
            'user_side'   => 'opposition',
            'persona_id'  => $persona->id,
            'difficulty'  => 'beginner',
            'mode'        => 'sparring',
            'language'    => 'en',
        ]);

        $debate = \App\Models\Debate::first();
        $this->assertNotNull($debate);
        $this->assertEquals('sparring', $debate->mode);

        $this->assertDatabaseHas('rounds', [
            'debate_id' => $debate->id,
            'phase'     => null,
        ]);

        $debateResponse = $this->get('/debates/' . $debate->id);
        $debateResponse->assertStatus(200);
        $debateResponse->assertSee('End Round &amp; Get Feedback', false);
        $debateResponse->assertSee('No fixed phases here');
    }

    public function test_mixed_language_is_rejected(): void
    {
        $persona = Persona::first();

        $this->post('/debates', [
            'motion_text' => 'This House believes X.',
            'user_side'   => 'government',
            'persona_id'  => $persona->id,
            'difficulty'  => 'beginner',
            'mode'        => 'tournament',
            'language'    => 'mixed',
        ])->assertSessionHasErrors('language');

        $this->assertDatabaseMissing('debates', [
            'language' => 'mixed',
        ]);
    }

    public function test_health_endpoint_reports_ok(): void
    {
        $response = $this->get('/healthz');
        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    public function test_unknown_debate_returns_404_page(): void
    {
        $this->get('/debates/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404)
            ->assertSee('This page is not in the House');
    }
}
