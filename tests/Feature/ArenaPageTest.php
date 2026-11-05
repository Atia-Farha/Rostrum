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
 * The debate arena, on a phone.
 *
 * The arena is a full-height workspace: two panes side by side, each
 * scrolling on its own, with a microphone the reader has to reach.
 *
 * It used to opt out of the application shell and carry a strip of its
 * own. That left the one page you cannot leave mid-round with no way
 * out of it, and — because the shell's navigation is a rail that
 * becomes a drawer on a narrow screen — with no navigation at all on
 * the device where a reader most needs a way out.
 *
 * These tests hold the arena to the shell it now shares with every
 * other page, and to the shape a phone needs.
 */
class ArenaPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PersonaSeeder::class);
    }

    private function makeDebate(User $user): Debate
    {
        $motion = Motion::create([
            'text_en'  => 'This House would ban social media for users under the age of 16.',
            'text_bn'  => 'এই সভা মনে করে যে ১৬ বছরের কম বয়সীদের জন্য সোশ্যাল মিডিয়া নিষিদ্ধ করা উচিত।',
            'category' => 'Society',
            'source'   => 'manual',
        ]);

        return Debate::create([
            'session_id' => 'session-'.uniqid(),
            'user_id'    => $user->id,
            'motion_id'  => $motion->id,
            'persona_id' => Persona::first()->id,
            'user_side'  => 'government',
            'difficulty' => 'intermediate',
            'mode'       => 'tournament',
            'language'   => 'en',
            'status'     => 'in_progress',
        ]);
    }

    private function arenaHtml(User $user): string
    {
        $debate = $this->makeDebate($user);

        return $this->actingAs($user)
            ->get(route('debates.show', $debate->id))
            ->assertOk()
            ->getContent();
    }

    /**
     * The arena is part of the workspace, so it carries the rail — and
     * with it, the drawer that is the only navigation a phone has.
     */
    public function test_the_arena_carries_the_workspaces_navigation(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('class="app-side"', $html);
        $this->assertStringContainsString('class="app-side-bar"', $html);
        $this->assertStringContainsString('class="app-side-scrim"', $html);
        $this->assertStringContainsString('has-side', $html);
    }

    /**
     * The drawer is opened from the shell's own bar. Without that
     * button the rail is off-screen on a phone and unreachable.
     */
    public function test_the_arena_can_open_the_navigation_drawer(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('class="app-side-open"', $html);
        $this->assertStringContainsString("sidebarOpen = true", $html);
    }

    /**
     * The bespoke strip is gone. Two bars would mean two ways out of a
     * round, and the arena's own bar was the one without any
     * destinations on it.
     */
    public function test_the_arena_no_longer_carries_a_bar_of_its_own(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringNotContainsString('arena-bar', $html);
        $this->assertStringNotContainsString('is-arena', $html);
    }

    /**
     * The arena needs a heading. It was the only page in the product
     * without one, so a screen reader announced "main" and nothing
     * about what was in it.
     */
    public function test_the_arena_has_exactly_one_heading(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertSame(
            1,
            substr_count($html, '<h1'),
            'The arena should have exactly one h1.',
        );
    }

    /**
     * The heading is the motion — the thing the round is about.
     */
    public function test_the_arena_heading_is_the_motion(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertMatchesRegularExpression(
            '/<h1[^>]*>\s*This House would ban social media[^<]*<\/h1>/',
            $html,
        );
    }

    /**
     * On a narrow screen the transcript and the podium cannot both be
     * on a phone. The arena shows one at a time and the reader
     * switches between them.
     *
     * The switcher is about reading — about what fills the screen.
     * It is not about reaching the microphone, which is docked below
     * and always present.
     */
    public function test_the_arena_offers_a_panel_switcher(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('class="arena-tabs"', $html);
        $this->assertStringContainsString("showPane('transcript')", $html);
        $this->assertStringContainsString("showPane('podium')", $html);
    }

    /**
     * The switcher points at real panels, and names them, so a screen
     * reader can describe what the button moves between.
     */
    public function test_the_panel_switcher_points_at_named_panels(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('id="transcript-pane"', $html);
        $this->assertStringContainsString('id="podium-pane"', $html);
        $this->assertStringContainsString('aria-controls="transcript-pane"', $html);
        $this->assertStringContainsString('aria-controls="podium-pane"', $html);
    }

    /**
     * The switcher is a pair of pressed-state toggles rather than
     * links to separate pages, because the panels are two views of one
     * round rather than two places.
     */
    public function test_the_panel_switcher_is_a_group_of_toggles(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('role="group"', $html);
        $this->assertStringContainsString('aria-pressed="pane === \'transcript\'"', $html);
        $this->assertStringContainsString('aria-pressed="pane === \'podium\'"', $html);
    }

    /**
     * The switcher marks where the round is waiting, but it no longer
     * moves on its own.
     *
     * It used to follow the turn, because the microphone lived inside
     * the podium panel and reaching it meant switching panels. That
     * made the reader leave the transcript to answer the argument in
     * it — which is the one thing a debater cannot do.
     *
     * Now the microphone is docked below and always present, so the
     * switcher is only a choice of what to read, and a choice the
     * reader keeps.
     */
    public function test_the_switcher_marks_the_turn_without_taking_the_view(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        // The round still reports where it is waiting, and the dot on
        // the other button still marks it.
        $this->assertStringContainsString('setPendingPane', $html);
        $this->assertStringContainsString("setPendingPane('podium')", $html);
        $this->assertStringContainsString("setPendingPane('transcript')", $html);
        $this->assertStringContainsString('paneBadge', $html);

        // But marking the turn must not move the reader's view.
        $this->assertStringNotContainsString(
            '$this->pane = where;',
            $html,
            'The switcher must not move itself when the turn changes.',
        );
    }

    /**
     * The microphone is docked at the foot of the screen on a narrow
     * viewport, so it is reachable without switching panels.
     *
     * This is the whole point of the dock: a reader answers an
     * argument they can see.
     */
    public function test_the_microphone_is_docked_below_the_panels(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('class="arena-dock"', $html);
        $this->assertStringContainsString('class="arena-dock-mic"', $html);
        $this->assertStringContainsString('aria-label="Round controls"', $html);
    }

    /**
     * The dock sits below the panels, not inside one of them — so it
     * is present whichever pane the reader has in front.
     */
    public function test_the_dock_is_outside_the_panel_switcher(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        // The dock's own state mirror lives on the arena, which wraps
        // both the layout and the dock.
        $this->assertStringContainsString('podiumState', $html);
        $this->assertStringContainsString('syncPodium', $html);

        // And the dock reports the round's state in words, so a reader
        // on the transcript can still see that it is their move.
        $this->assertStringContainsString('arena-dock-status', $html);
        $this->assertStringContainsString('Your turn', $html);
        $this->assertStringContainsString('Opponent responding', $html);
    }

    /**
     * There must never be two microphones on screen.
     *
     * On a phone the dock holds the microphone and the podium's own
     * copy is hidden; on a wide screen the podium holds it and the
     * dock is hidden. Two live microphones would be one button too
     * many, and a reader would not know which one is the real one.
     */
    public function test_only_one_microphone_is_shown_at_a_time(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        // The podium's copy is marked so the narrow rules can hide it.
        $this->assertStringContainsString('class="podium-mic"', $html);

        // And the narrow rules hide it, along with the podium's status
        // pill and clock, so the dock is the one place on a phone that
        // says what the round is doing.
        $this->assertStringContainsString('.podium-col .podium-mic', $html);
        $this->assertStringContainsString('.podium-col .podium-status', $html);
        $this->assertStringContainsString('.podium-col .podium-timer', $html);
    }

    /**
     * The clock moves to the dock while recording.
     *
     * A time limit the reader cannot see is not a limit they can
     * respect — and on a phone they are usually looking at the
     * transcript rather than the podium.
     */
    public function test_the_dock_carries_the_clock(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('class="arena-dock-timer"', $html);
        $this->assertStringContainsString('formatClock', $html);
        $this->assertStringContainsString('podiumTimeLeft', $html);
    }

    /**
     * Alpine calls a component's init() on its own, from x-data. Adding
     * x-init="init()" as well called it a second time.
     *
     * That was not harmless: on a debate where the opponent speaks
     * first, the opening speech was requested twice — two Gemini
     * calls and two speech syntheses for one speech — and the reader
     * saw the round start twice.
     */
    public function test_the_arena_components_are_initialised_once(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringNotContainsString(
            'x-init="init()"',
            $html,
            'Alpine already calls init() from x-data; calling it again double-initialises the component.',
        );
    }

    /**
     * The arena fills the viewport rather than scrolling the page. A
     * round that scrolled the whole document put the microphone a
     * screen away from the transcript being read.
     */
    public function test_the_arena_fills_the_viewport_instead_of_scrolling_the_page(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('class="arena-shell"', $html);
        $this->assertStringContainsString('height: 100dvh', $html);
        $this->assertStringContainsString('overflow: hidden', $html);
    }

    /**
     * The two panes scroll on their own. Without a bounded height on
     * each, a long speech pushes the whole page into a second scroll.
     */
    public function test_each_pane_scrolls_on_its_own(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        // The feed and the podium body both need min-height: 0 to be
        // allowed to shrink inside the grid, and their own overflow.
        $this->assertStringContainsString('.transcript-feed {', $html);
        $this->assertStringContainsString('.podium-body {', $html);
        $this->assertStringContainsString('overflow-y: auto', $html);
    }

    /**
     * The narrow-screen rules have to exist. The old layout had one
     * breakpoint that stacked the panes and let the page scroll; a
     * phone needs the panels to take turns instead.
     */
    public function test_the_narrow_layout_is_stated_in_the_stylesheet(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('@media (max-width: 900px)', $html);
        $this->assertStringContainsString('.arena-tabs {', $html);
        $this->assertStringContainsString('display: flex', $html);
    }

    /**
     * The shell's bar takes the top of the screen below 1024px, so the
     * arena has to take what is left rather than a screen's worth of
     * scrollbar.
     */
    public function test_the_arena_claims_the_space_the_shell_leaves(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('@media (max-width: 1024px)', $html);
        $this->assertStringContainsString('height: calc(100dvh - 52px)', $html);
    }

    /**
     * The shell's height constant and the arena's own assumption have
     * to be the same number. The arena used to subtract a bar that no
     * longer existed, which is why it grew a screen of scroll.
     */
    public function test_the_arena_and_the_shell_agree_on_the_bar_height(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        // The shell's mobile bar is 52px (resources/css/app.css).
        $this->assertStringContainsString('52px', $html);
    }

    /**
     * A printed round is the transcript, in order, on the page. The
     * controls that manage it have no meaning on paper.
     */
    public function test_the_arena_prints_as_a_transcript(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('@media print', $html);
        $this->assertStringContainsString('.podium-col {', $html);
    }

    /**
     * The tabs are the only way to reach the microphone on a phone, so
     * they must be real buttons — focusable, and operable with Enter
     * and Space.
     */
    public function test_the_panel_switcher_is_operable_by_keyboard(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('<button type="button"', $html);
        $this->assertStringContainsString('class="arena-tab"', $html);
    }

    /**
     * The transcript and the podium are each a landmark of their own,
     * so a screen reader can be sent straight to the one the reader
     * wants.
     */
    public function test_the_arena_labels_its_panels(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        $this->assertStringContainsString('aria-label="Arena panels"', $html);
        $this->assertStringContainsString('aria-label="Main content"', $html);
    }

    /**
     * The opponent's speech arrives complete, so it is shown complete.
     *
     * It used to type itself out a character at a time, timed to finish
     * as the audio did. That made the reader wait through the whole
     * speech before they could read it, and it meant the words on screen
     * were never the words they could have read a second earlier.
     */
    public function test_the_opponents_speech_is_shown_in_full_at_once(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        // The bubble the speech goes into.
        $this->assertStringContainsString('ai-speech', $html);

        // The speech is put into the bubble in one assignment.
        $this->assertStringContainsString('el.textContent = text;', $html);

        // And there is no character-by-character typewriter left.
        $this->assertStringNotContainsString('typewriterSynced', $html);
        $this->assertStringNotContainsString('text.slice(0, i)', $html);
    }

    /**
     * The typewriter used to time the end of the turn, so removing it
     * must not leave the round hanging. The microphone is released when
     * the opponent's speech ends — not when the text finishes being
     * typed, because that would let the reader record over the AI.
     */
    public function test_the_turn_ends_when_the_opponent_finishes_speaking(): void
    {
        $html = $this->arenaHtml(User::factory()->create());

        // The speech ending ends the turn.
        $this->assertStringContainsString('audioEl.onended', $html);
        $this->assertStringContainsString('finishTurn();', $html);

        // And the turn cannot be ended twice.
        $this->assertStringContainsString('if (turnFinished) return;', $html);
    }
}
