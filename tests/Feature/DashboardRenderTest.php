<?php

namespace Tests\Feature;

use App\Models\Adjudication;
use App\Models\Debate;
use App\Models\Motion;
use App\Models\Persona;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PersonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders every signed-in page and asserts the markup comes back whole.
 *
 * This is a render smoke test, not a design test: it catches a broken
 * Blade directive, a missing variable, or a fatal error in the new
 * markup, which a unit test on the controllers would not.
 */
class DashboardRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PersonaSeeder::class);
    }

    /**
     * Creates a debate owned by $user. When $scored is true the debate
     * also carries an adjudication, so the pages can be exercised in
     * both their scored and unscored states.
     */
    private function makeDebate(User $user, bool $scored = false): Debate
    {
        $motion = Motion::create([
            'text_en'   => 'This House believes that open-sourced AI should be regulated.',
            'text_bn'   => 'এই সভা মনে করে যে ওপেন-সোর্সড এআই নিয়ন্ত্রণ করা উচিত।',
            'category'  => 'Technology',
            'source'    => 'manual',
        ]);

        $debate = Debate::create([
            'session_id'   => 'session-'.uniqid(),
            'user_id'      => $user->id,
            'motion_id'    => $motion->id,
            'persona_id'   => Persona::first()->id,
            'user_side'    => 'government',
            'difficulty'   => 'advanced',
            'mode'         => 'tournament',
            'language'     => 'en',
            'status'       => $scored ? 'adjudicated' : 'in_progress',
        ]);

        if ($scored) {
            Adjudication::create([
                'debate_id'       => $debate->id,
                'total_score'     => 78,
                'matter_score'    => 30,
                'manner_score'    => 23,
                'method_score'    => 25,
                'verdict'         => 'A disciplined performance with one weak warrant.',
                'fallacies'       => [[
                    'phase'       => 'Rebuttal',
                    'type'        => 'Unwarranted Assumption',
                    'explanation' => 'The claim was never supported.',
                ]],
                'feedback_bullets' => [
                    '[STRENGTH] A clear opening framing.',
                    '[TIP] Cite a named study.',
                    '[ISSUE] The rebuttal never answered the case.',
                ],
            ]);
        }

        return $debate;
    }

    public function test_overview_page_renders(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Overview')
            ->assertSee('Recent Debates')
            ->assertSee('Ada');
    }

    public function test_overview_renders_with_adjudicated_and_pending_debates(): void
    {
        $user = User::factory()->create();

        // One scored debate and one pending debate.
        $this->makeDebate($user, scored: true);
        $this->makeDebate($user);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Rubric Averages');
    }

    public function test_the_reports_tile_counts_the_reports_and_not_the_debates(): void
    {
        $user = User::factory()->create();

        // Two scored debates and one that is still running. All three
        // are tournament rounds, but only two produced a report.
        $this->makeDebate($user, scored: true);
        $this->makeDebate($user, scored: true);
        $this->makeDebate($user);

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        // Scoped to the reports tile. The debates tile beside it
        // legitimately counts every debate by format, so a page-wide
        // search would find "3 tournament" there and call it a bug.
        $tile = $this->statTile($html, 'Reports');

        $this->assertNotSame('', $tile, 'The reports tile was not found on the page.');

        // The tile's value is the number of reports, and the note
        // under it describes that same set. The note used to count
        // every debate by format, so "3 tournament" sat under a value
        // of 2 — two different numbers, presented as one fact.
        $this->assertStringContainsString('>2</span>', $tile);
        $this->assertStringContainsString('2 tournament', $tile);
        $this->assertStringNotContainsString('3 tournament', $tile);
    }

    /**
     * The markup of the one stat tile carrying the given label.
     */
    private function statTile(string $html, string $label): string
    {
        $start = strpos($html, '>' . $label . '<');

        if ($start === false) {
            return '';
        }

        $tileStart = strrpos(substr($html, 0, $start), '<div class="surf-stat"');

        if ($tileStart === false) {
            return '';
        }

        $end = strpos($html, '</div>', $start);

        return $end === false ? '' : substr($html, $tileStart, $end - $tileStart + 6);
    }

    public function test_the_overview_plots_the_accounts_own_scores(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', config('debate.seed_account_email'))->first();

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertOk();

        // The account owns the seeded debate, so its score is plotted on
        // the trend chart and broken down on the rubric chart. The demo
        // debate belongs to the demo account alone, so it is not counted
        // in this account's statistics.
        $response->assertSee('Score Trend');
        $response->assertSee('Rubric Averages');
        $response->assertSee('Reports by Difficulty');

        $seedDebate = Debate::where('session_id', config('debate.seed_session_id'))->first();
        $score = $seedDebate->adjudication->total_score;

        // The reading is written into the markup beside the point, so
        // the chart can be read without relying on colour.
        $response->assertSee((string) $score, false);
    }

    public function test_the_overview_does_not_count_the_demo_debate_for_another_account(): void
    {
        $this->seed(DatabaseSeeder::class);

        $viewer = User::factory()->create();

        $html = $this->actingAs($viewer)->get(route('dashboard'))->getContent();

        // The seeded demo debate belongs to the demo account. It never
        // reaches another account's overview, so it cannot be counted in
        // someone else's statistics — that would put another account's
        // practice on their dashboard.
        $this->assertStringContainsString('No scores yet', $html);
        $this->assertStringNotContainsString('Sample Report', $html);
    }

    public function test_history_page_renders(): void
    {
        $user = User::factory()->create();
        $this->makeDebate($user, scored: true);

        $this->actingAs($user)
            ->get(route('history.index'))
            ->assertOk()
            ->assertSee('Debate History')
            ->assertSee('Tournament');
    }

    public function test_account_settings_page_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.index'))
            ->assertOk()
            ->assertSee('Account Settings')
            ->assertSee('Danger Zone');
    }

    public function test_setup_page_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('setup'))
            ->assertOk()
            ->assertSee('Configure Debate Session');
    }

    public function test_feedback_report_page_renders(): void
    {
        $user = User::factory()->create();
        $debate = $this->makeDebate($user, scored: true);

        $this->actingAs($user)
            ->get(route('debates.feedback', $debate->id))
            ->assertOk()
            ->assertSee('Adjudication Report')
            ->assertSee('Scorecard')
            ->assertSee('Findings')
            ->assertSee('Reasoning errors')
            ->assertSee('Unwarranted Assumption');
    }

    public function test_shell_navigation_renders_on_signed_in_pages(): void
    {
        $user = User::factory()->create();

        // The signed-in workspace is reached through the sidebar rail.
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('app-side', false)
            ->assertSee('app-side-link', false);
    }

    public function test_the_arena_carries_the_same_shell_as_every_other_page(): void
    {
        $user = User::factory()->create();
        $debate = $this->makeDebate($user);

        $response = $this->actingAs($user)->get(route('debates.show', $debate->id));

        $response->assertOk();

        // The arena is part of the workspace, so it takes the rail like
        // every other signed-in page. It used to opt out and carry a
        // strip of its own, which left the one page you cannot leave
        // mid-round with no way out of it — and on a phone, where the
        // rail is the only navigation, with no navigation at all.
        $response->assertSee('app-side', false);
        $response->assertSee('app-side-link', false);
        $response->assertSee('has-side', false);

        // The old strip is gone, and so is the opt-out that asked for
        // it. Neither should come back.
        $response->assertDontSee('arena-bar', false);
        $response->assertDontSee('is-arena', false);
    }

    public function test_the_arena_offers_a_way_out_of_a_round(): void
    {
        $user = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->actingAs($user)->get(route('debates.show', $debate->id))->getContent();

        // The rail's own destinations have to be reachable from the
        // arena, not only from the dashboard. The arena is where a
        // reader lands after a round, and it is the one page they
        // arrive at from a link rather than a click.
        foreach (['Dashboard', 'History', 'Settings'] as $label) {
            $this->assertStringContainsString(
                '>'.$label.'<',
                $this->railNav($html) ?? '',
                "The arena's rail should offer \"{$label}\".",
            );
        }
    }

    public function test_the_arena_has_a_panel_switcher_for_narrow_screens(): void
    {
        $user = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->actingAs($user)->get(route('debates.show', $debate->id))->getContent();

        // Below 900px the transcript and the podium cannot both be on a
        // phone screen, so one is shown at a time and the reader
        // switches between them. Without this the mic — the one control
        // the reader has to reach — sat a full screen below the
        // transcript.
        $this->assertStringContainsString('arena-tabs', $html);
        $this->assertStringContainsString('showPane(\'transcript\')', $html);
        $this->assertStringContainsString('showPane(\'podium\')', $html);

        // Each panel is named, so the switcher's buttons point at
        // something a screen reader can describe.
        $this->assertStringContainsString('id="transcript-pane"', $html);
        $this->assertStringContainsString('id="podium-pane"', $html);
    }

    public function test_the_rail_lists_every_destination_exactly_once(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        // Scoped to the rail: the page itself also carries an "Overview"
        // eyebrow, and counting the whole document would measure that
        // rather than a repeat in the navigation.
        $nav = $this->railNav($html);

        $this->assertNotNull($nav, 'The rail should render a navigation block.');

        // The rail is the one place the destinations live. A second copy
        // of any of them — in an account popover, say — showed the same
        // links twice in the same panel, which read as clutter on a
        // phone where the whole rail is on screen at once.
        foreach (['Dashboard', 'New Debate', 'History', 'Settings', 'Sign Out'] as $label) {
            $this->assertSame(
                1,
                substr_count($nav, '>'.$label.'<'),
                "The rail should list \"{$label}\" exactly once.",
            );
        }
    }

    /**
     * Pull the rail's navigation element out of a rendered page.
     */
    private function railNav(string $html): ?string
    {
        return preg_match('/<nav class="app-side-nav".*?<\/nav>/s', $html, $m) ? $m[0] : null;
    }

    public function test_the_rail_offers_signing_out_without_a_popover(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        // Signing out is a row in the rail beside the other account
        // action. It used to hide behind a trigger that opened a
        // popover holding one button.
        $this->assertStringContainsString('Sign Out', $html);
        $this->assertStringContainsString('app-side-link is-danger', $html);

        // And there is no account popover left in the rail to hold
        // anything else.
        $this->assertStringNotContainsString('app-account-menu', $html);
        $this->assertStringNotContainsString('app-account-trigger', $html);
    }

    public function test_the_rail_shows_the_signed_in_person_and_not_a_repeat_of_them(): void
    {
        $user = User::factory()->create(['name' => 'Grace Hopper']);

        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

        // The account card states the name once. A second copy inside a
        // menu would show the same two lines twice, one above the other.
        $this->assertSame(1, substr_count($html, 'Grace Hopper'));
        $this->assertStringNotContainsString('app-account-meta', $html);
    }
}
