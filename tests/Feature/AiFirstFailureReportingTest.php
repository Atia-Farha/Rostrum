<?php

namespace Tests\Feature;

use App\Models\Debate;
use App\Models\Motion;
use App\Models\Persona;
use App\Models\User;
use Database\Seeders\PersonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * When the opponent fails to speak, the reader is told why.
 *
 * The opponent opens the round when the reader chose Opposition. The
 * server answers that request with `ai_text` set to null and the
 * reason in `ai_error` — it is not a validation error, so `error` and
 * `errors` are both absent from the payload.
 *
 * The page used to check only those two absent fields. A failed opening
 * therefore produced an EMPTY speech bubble, no explanation, and the
 * podium handing the microphone straight back. It looked like the
 * opponent had simply said nothing, and there was no way to ask it to
 * try again.
 *
 * These tests hold the arena to reading the failure and offering a
 * retry.
 */
class AiFirstFailureReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PersonaSeeder::class);
    }

    private function arenaHtml(User $user): string
    {
        $motion = Motion::create([
            'text_en'  => 'This House would abolish all monetary policy.',
            'text_bn'  => 'এই সভা মনে করে যে সব মুদ্রা নীতি বাতিল করা উচিত।',
            'category' => 'Economics',
            'source'   => 'manual',
        ]);

        $debate = Debate::create([
            'session_id' => 'session-'.uniqid(),
            'user_id'    => $user->id,
            'motion_id'  => $motion->id,
            'persona_id' => Persona::first()->id,
            'user_side'  => 'opposition',
            'difficulty' => 'intermediate',
            'mode'       => 'tournament',
            'language'   => 'en',
            'status'     => 'in_progress',
        ]);

        return $this->actingAs($user)
            ->get(route('debates.show', $debate->id))
            ->assertOk()
            ->getContent();
    }

    /**
     * The failure flag has to look at the fields the server actually
     * uses to report a failed opening.
     */
    public function test_a_failed_opening_is_detected_from_the_fields_the_server_uses(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('data.ai_error', $html);
        $this->assertStringContainsString('!data.ai_text', $html);
    }

    /**
     * The reason has to reach the reader, and a retry has to be offered.
     */
    public function test_a_failed_opening_shows_the_reason_and_offers_a_retry(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('showAiError', $html);
        $this->assertStringContainsString('retryAiGeneration', $html);
        $this->assertStringContainsString('btn-retry-ai-response', $html);
    }

    /**
     * The reader has not spoken yet when the opponent opens the round,
     * so the error must not claim their speech was saved.
     */
    public function test_the_error_does_not_claim_the_reader_spoke_already(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('The opponent has not spoken yet.', $html);
    }
}
