<?php

namespace Tests\Feature;

use App\Models\Debate;
use App\Models\Motion;
use App\Models\Persona;
use App\Models\Round;
use App\Models\Turn;
use App\Models\User;
use App\Services\DebateRoundEngine;
use Database\Seeders\PersonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The phase timeline has to follow the phase.
 *
 * The timeline — Opening, Rebuttal, Closing — was rendered on the server
 * and never touched again. The page did not have to reload for the round
 * to advance: when the opponent opened the next phase, the debate moved
 * on in place, without a reload. The timeline did not move with it.
 *
 * So the reader was told they were in the Opening while the debate was
 * already in the Rebuttal, and the only way to find out where they
 * actually were was to reload the page.
 *
 * It showed up most in Opposition mode, where the opponent opens
 * Opening and Rebuttal and the page is never reloaded between them.
 *
 * These tests hold the arena to moving the timeline with the phase.
 */
class PhaseTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PersonaSeeder::class);
    }

    private function makeDebate(User $user, string $userSide = 'opposition'): Debate
    {
        $motion = Motion::create([
            'text_en'  => 'This House believes that developed nations should pay financial reparations to developing countries for climate-related damages.',
            'text_bn'  => 'এই সভা মনে করে যে উন্নয়নশীল দেশগুলোর জলবায়ু-সংক্রান্ত ক্ষতির জন্য উন্নয়নশীল দেশগুলোকে আর্থিক ক্ষতিপূরণ দিতে হবে।',
            'category' => 'Environment',
            'source'   => 'manual',
        ]);

        $debate = Debate::create([
            'session_id' => 'session-'.uniqid(),
            'user_id'    => $user->id,
            'motion_id'  => $motion->id,
            'persona_id' => Persona::first()->id,
            'user_side'  => $userSide,
            'difficulty' => 'intermediate',
            'mode'       => 'tournament',
            'language'   => 'en',
            'status'     => 'in_progress',
        ]);

        app(DebateRoundEngine::class)->seedFirstRound($debate);

        return $debate;
    }

    private function arenaHtml(Debate $debate, User $user): string
    {
        return $this->actingAs($user)
            ->get(route('debates.show', $debate->id))
            ->assertOk()
            ->getContent();
    }

    /**
     * Each step on the timeline says which phase it is, so the page can
     * move the marker to it without a reload.
     */
    public function test_the_timeline_steps_name_their_phase(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->arenaHtml($debate, $user);

        $this->assertStringContainsString('data-phase="opening"', $html);
        $this->assertStringContainsString('data-phase="rebuttal"', $html);
        $this->assertStringContainsString('data-phase="closing"', $html);
    }

    /**
     * The timeline is moved in the browser, at the moment the phase
     * changes.
     */
    public function test_the_timeline_can_be_moved_without_a_reload(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->arenaHtml($debate, $user);

        $this->assertStringContainsString('setActivePhase', $html);
        $this->assertStringContainsString(".timeline-step[data-phase]", $html);
    }

    /**
     * The phase advance has to reach the timeline on every path that
     * advances a phase.
     *
     * The opponent opening the next phase is the path that skips the
     * reload — the one where the timeline was left behind. The reader
     * opening the next phase reloaded the page, which is why the bug
     * looked like it only happened in one direction.
     */
    public function test_every_phase_advance_moves_the_timeline(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->arenaHtml($debate, $user);

        // The advance is routed through one place, so no path can move
        // the phase without moving the timeline with it.
        $this->assertStringContainsString('advancePhase(phase)', $html);
        $this->assertStringContainsString('window.debateArena?.setActivePhase(phase);', $html);
    }

    /**
     * The opponent opening the next phase is the path that never
     * reloaded, so it has to move the timeline itself.
     */
    public function test_the_opponent_opening_a_phase_moves_the_timeline(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->arenaHtml($debate, $user);

        $this->assertStringContainsString('this.advancePhase(data.new_phase);', $html);
    }

    /**
     * The path where the opponent speaks first used to end the round's
     * turn without looking at whether the round had ended.
     *
     * When the reader chose Opposition, the reader opens the Closing
     * phase — so the opponent's speech can close a phase, and the
     * debate has to move on. The old code went straight to 'idle' and
     * left the timeline on the phase that had just ended.
     */
    public function test_the_opponents_own_speech_can_end_a_phase_and_the_timeline_follows(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->arenaHtml($debate, $user);

        // triggerAiFirst has to look at the progression the server
        // reported, the same as every other path.
        $this->assertStringContainsString('data.round_complete && data.new_phase', $html);
        $this->assertStringContainsString('data.debate_complete', $html);
    }

    /**
     * The reader opening the next phase used to reload the page to make
     * the timeline catch up. The reload is no longer needed — the
     * timeline moves in the page.
     */
    public function test_the_reader_opening_a_phase_no_longer_needs_a_reload(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->arenaHtml($debate, $user);

        // The reader's turn that completes a phase moves the timeline
        // and hands back the microphone, without a reload.
        $this->assertStringContainsString('this.advancePhase(data.new_phase);', $html);
    }

    /**
     * The transcript says where a phase begins, so the feed does not run
     * from the last speech of one phase into the first speech of the
     * next with nothing between them.
     */
    public function test_a_phase_divider_is_added_when_the_phase_changes(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->arenaHtml($debate, $user);

        $this->assertStringContainsString('showPhaseDivider', $html);
        $this->assertStringContainsString('window.debateArena?.showPhaseDivider(phase);', $html);
    }

    /**
     * One divider per phase. A phase that is announced twice has two
     * lines across the transcript, which reads as the debate having
     * started that phase twice.
     */
    public function test_a_phase_is_never_divided_twice(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->arenaHtml($debate, $user);

        // The divider carries the phase it belongs to, and a phase that
        // is already marked is left alone.
        $this->assertStringContainsString('div.dataset.phase = phase;', $html);
        $this->assertStringContainsString('.phase-divider[data-phase="${phase}"]', $html);
    }

    /**
     * A phase that was rendered on the server must not be announced
     * again in the browser.
     *
     * The server-rendered dividers sit outside the dynamic area, so the
     * check has to reach the whole transcript rather than the part the
     * browser fills in.
     */
    public function test_a_server_rendered_phase_is_not_announced_again(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        // Give the debate a second phase, with a divider rendered for
        // it on the server.
        app(DebateRoundEngine::class)->advancePhase($debate);

        $html = $this->arenaHtml($debate, $user);

        $this->assertStringContainsString('class="phase-divider" data-phase="rebuttal"', $html);
        $this->assertStringContainsString("document.getElementById('transcript-feed')", $html);
    }

    /**
     * The phase the reader is on is the phase the server is running, and
     * the client keeps up with it.
     */
    public function test_the_phase_the_client_tracks_starts_on_the_servers_phase(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->arenaHtml($debate, $user);

        // The client is handed the server's phase, not a guess of its own.
        $this->assertStringContainsString('currentPhase', $html);
        $this->assertStringContainsString('opening', $html);
    }

    /**
     * When the opponent opens the round, the debate does not wait for a
     * reload to move on.
     *
     * This is the whole shape of the bug: a page that stays put while
     * the phase under it changes.
     */
    public function test_a_debate_can_advance_a_phase_without_the_page_reloading(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);
        $engine = app(DebateRoundEngine::class);

        // The opponent opens the round, then the reader answers: the
        // phase is complete and the next one begins.
        $opening = $debate->rounds()->first();
        Turn::create([
            'id'         => (string) \Illuminate\Support\Str::uuid(),
            'round_id'   => $opening->id,
            'speaker'    => 'ai',
            'transcript' => 'The Government opens the debate.',
            'audio_path' => null,
        ]);
        Turn::create([
            'id'         => (string) \Illuminate\Support\Str::uuid(),
            'round_id'   => $opening->id,
            'speaker'    => 'user',
            'transcript' => 'The Opposition responds.',
            'audio_path' => null,
        ]);

        $this->assertTrue($engine->isRoundComplete($opening->fresh()));

        $next = $engine->advancePhase($debate);
        $this->assertNotNull($next);
        $this->assertSame('rebuttal', $next->phase);

        // The server is now in the Rebuttal...
        $this->assertSame('rebuttal', $engine->currentRound($debate)->phase);

        // ...and the page says so without being reloaded.
        $html = $this->arenaHtml($debate, $user);
        $this->assertStringContainsString('setActivePhase', $html);
        $this->assertStringContainsString('this.advancePhase(data.new_phase);', $html);
    }
}
