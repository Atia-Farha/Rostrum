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
 * The history page is the product's ledger: every session on record,
 * with the facts needed to find one again.
 *
 * These tests cover what the page promises — that the rows are the
 * user's own sessions, that the facts about a session travel with it,
 * that the seeded demo debate belongs to the demo account alone, and
 * that the page is operable without sight of the screen.
 */
class HistoryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PersonaSeeder::class);
    }

    // ── The ledger itself ─────────────────────────────────────────────────

    public function test_the_history_page_lists_the_viewers_own_sessions(): void
    {
        $user = User::factory()->create();
        $motion = $this->createMotion('This House believes records should be readable.');
        $debate = $this->createDebateFor($user, $motion);

        $response = $this->actingAs($user)->get(route('history.index'));

        $response->assertStatus(200);
        $response->assertSee('This House believes records should be readable.');
        $response->assertSee(route('debates.show', $debate->id), false);
    }

    public function test_the_history_page_opens_on_the_format_the_reader_has_records_for(): void
    {
        $user = User::factory()->create();
        $this->createDebateFor($user, $this->createMotion('A sparring motion.'), mode: 'sparring');

        $response = $this->actingAs($user)->get(route('history.index'));

        // The sparring tab is the one with records, so the page opens
        // there rather than on an empty tab.
        $response->assertSee("tab: 'sparring'", false);
    }

    public function test_the_history_page_does_not_list_another_users_sessions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->createDebateFor($other, $this->createMotion('Another users motion.'));

        $response = $this->actingAs($user)->get(route('history.index'));

        $response->assertDontSee('Another users motion.');
    }

    // ── The facts about a session ─────────────────────────────────────────

    public function test_each_row_carries_its_format_side_and_difficulty(): void
    {
        $user = User::factory()->create();
        $this->createDebateFor($user, $this->createMotion('A motion with facts.'));

        $response = $this->actingAs($user)->get(route('history.index'));

        // These travel under the motion as tags, so the motion keeps the
        // room that a column of its own would take.
        $response->assertSee('Tournament');
        $response->assertSee('Government');
        $response->assertSee('Intermediate');
    }

    public function test_a_session_in_progress_is_marked_as_such_and_carries_no_score(): void
    {
        $user = User::factory()->create();
        $this->createDebateFor($user, $this->createMotion('An unfinished motion.'));

        $response = $this->actingAs($user)->get(route('history.index'));

        $response->assertSee('In progress');
        $response->assertSee('Not yet scored');
    }

    public function test_an_adjudicated_session_carries_its_score(): void
    {
        $user = User::factory()->create();
        $debate = $this->createDebateFor($user, $this->createMotion('A judged motion.'));

        Adjudication::create([
            'debate_id' => $debate->id,
            'matter_score' => 26,
            'manner_score' => 26,
            'method_score' => 26,
            'total_score' => 78,
            'fallacies' => [],
            'feedback_bullets' => [],
            'verdict' => 'The Government carried the motion.',
        ]);

        $response = $this->actingAs($user)->get(route('history.index'));

        $response->assertSee('Adjudicated');
        $response->assertSee('78');
        $response->assertDontSee('Not yet scored');
    }

    public function test_a_bangla_session_is_tagged_but_an_english_one_is_not(): void
    {
        $user = User::factory()->create();
        $this->createDebateFor($user, $this->createMotion('An english motion.'));
        $this->createDebateFor($user, $this->createMotion('একটি বাংলা প্রস্তাব।'), language: 'bn');

        $response = $this->actingAs($user)->get(route('history.index'));

        // English is every session's baseline; repeating it on every row
        // is noise. A Bangla session is worth naming.
        $response->assertSee('Bangla');
        $this->assertSame(1, substr_count($response->getContent(), '>Bangla<'));
    }

    // ── The seeded demo debate ───────────────────────────────────────────

    public function test_the_seeded_demo_debate_does_not_reach_another_accounts_record(): void
    {
        $this->seed(DatabaseSeeder::class);

        $reader = User::factory()->create();

        $response = $this->actingAs($reader)->get(route('history.index'));

        $response->assertStatus(200);

        // The demo debate belongs to the demo account. An account's
        // record is a record of that account's own practice, so the demo
        // is shown by signing in as the demo — not by being pushed into
        // every new account's ledger.
        $seedDebate = Debate::where('session_id', config('debate.seed_session_id'))->first();
        $response->assertDontSee(route('debates.feedback', $seedDebate->id), false);
    }

    public function test_the_demo_account_sees_the_seeded_demo_debate_on_its_record(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', config('debate.seed_account_email'))->first();

        $response = $this->actingAs($owner)->get(route('history.index'));

        $response->assertStatus(200);

        $seedDebate = Debate::where('session_id', config('debate.seed_session_id'))->first();
        $response->assertSee(route('debates.feedback', $seedDebate->id), false);
    }

    public function test_a_new_accounts_record_is_empty(): void
    {
        $this->seed(DatabaseSeeder::class);

        $reader = User::factory()->create();

        $response = $this->actingAs($reader)->get(route('history.index'));

        $response->assertStatus(200);

        // The page says the record is empty rather than showing an
        // empty table with nothing to explain it.
        $response->assertSee('No sessions on record');
        $response->assertSee('Start First Debate');
    }

    public function test_an_empty_record_opens_on_the_primary_format(): void
    {
        $reader = User::factory()->create();

        $response = $this->actingAs($reader)->get(route('history.index'));

        // With nothing on record there is no format to prefer, so the
        // page opens on Tournament — the primary format, and the first
        // tab on the page — rather than on the second one.
        $response->assertSee("tab: 'tournament'", false);
    }

    // ── Operable without sight of the screen ──────────────────────────────

    public function test_the_table_names_its_columns_and_its_content(): void
    {
        $user = User::factory()->create();
        $this->createDebateFor($user, $this->createMotion('An accessible motion.'));

        $response = $this->actingAs($user)->get(route('history.index'));

        // A caption gives the table a name; a header row with scope gives
        // each column a name. Without them a screen reader reads the
        // rows as an undifferentiated list of facts.
        $response->assertSee('<caption', false);
        $response->assertSee('Every debate session on record', false);
        $response->assertSee('scope="col"', false);
    }

    public function test_the_sortable_columns_announce_their_direction(): void
    {
        $user = User::factory()->create();
        $this->createDebateFor($user, $this->createMotion('A sortable motion.'));

        $response = $this->actingAs($user)->get(route('history.index'));

        // aria-sort is how the direction reaches a screen reader; the
        // drawn caret only carries it visually.
        $response->assertSee('aria-sort', false);
        $response->assertSee("sort === 'newest' ? 'descending'", false);
    }

    public function test_the_record_count_is_announced_when_it_changes(): void
    {
        $user = User::factory()->create();
        $this->createDebateFor($user, $this->createMotion('A counted motion.'));

        $response = $this->actingAs($user)->get(route('history.index'));

        // The count lives in a live region, so narrowing the list is
        // announced rather than being something the reader has to
        // notice.
        $response->assertSee('aria-live="polite"', false);
        $response->assertSee('x-text="summary()"', false);
    }

    public function test_the_empty_state_is_a_status_region(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('history.index'));

        $response->assertStatus(200);
        // The list can come up empty because a filter excluded
        // everything, and that is announced rather than shown as a
        // blank panel.
        $response->assertSee('role="status"', false);
    }

    public function test_the_filter_controls_are_labelled(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('history.index'));

        $response->assertSee('aria-label="Search debates by motion"', false);
        $response->assertSee('aria-label="Filter by status"', false);
        $response->assertSee('aria-label="Sort sessions"', false);
    }

    public function test_the_format_segments_are_a_tablist(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('history.index'));

        $response->assertSee('role="tablist"', false);
        $response->assertSee('role="tab"', false);
        $response->assertSee(':aria-selected', false);
    }

    public function test_the_rows_carry_the_facts_the_filters_read(): void
    {
        $user = User::factory()->create();
        $this->createDebateFor($user, $this->createMotion('A filterable motion.'));

        $response = $this->actingAs($user)->get(route('history.index'));

        // The filters run against the row's own attributes, so what is
        // on screen and what is filtered are the same set of facts.
        $response->assertSee('data-mode="tournament"', false);
        $response->assertSee('data-status="in_progress"', false);
        $response->assertSee('data-motion=', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function createMotion(string $text): Motion
    {
        return Motion::create([
            'text_en' => $text,
            'text_bn' => null,
            'source' => 'manual',
        ]);
    }

    private function createDebateFor(
        User $user,
        Motion $motion,
        string $mode = 'tournament',
        string $language = 'en',
    ): Debate {
        return Debate::create([
            'session_id' => 'history-test-'.uniqid(),
            'user_id' => $user->id,
            'motion_id' => $motion->id,
            'user_side' => 'government',
            'persona_id' => Persona::first()->id,
            'difficulty' => 'intermediate',
            'mode' => $mode,
            'language' => $language,
            'status' => 'in_progress',
        ]);
    }
}
