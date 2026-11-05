<?php

namespace Tests\Feature;

use App\Models\Debate;
use App\Models\Motion;
use App\Models\Persona;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PersonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PersonaSeeder::class);
    }

    // ── Registration ───────────────────────────────────────────────────────

    public function test_registration_screen_renders(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
        $response->assertSee('Create your account');
    }

    public function test_registration_screen_lists_every_password_rule_before_any_input(): void
    {
        // The rules must be on screen before the field is touched. A rule
        // that only appears after a mistake is a rule the person already
        // broke, so each one is spelled out up front.
        $response = $this->get('/register');

        $response->assertSee('10 or more characters');
        $response->assertSee('One lowercase letter');
        $response->assertSee('One uppercase letter');
        $response->assertSee('One number');

        // And the checklist must not be hidden behind a keystroke.
        $response->assertDontSee('x-show="password.length > 0"', false);
    }

    public function test_registration_screen_exposes_the_password_policy_to_assistive_technology(): void
    {
        $response = $this->get('/register');

        // The input points at the policy, and the policy is a real node with
        // a real id, so a screen reader is given the rule in one pass.
        $response->assertSee('aria-describedby="password-policy-sentence"', false);
        $response->assertSee('id="password-policy-sentence"', false);

        // The live count is a status region, so its changes are announced.
        $response->assertSee('role="status"', false);
    }

    public function test_registration_screen_reports_a_password_mismatch_against_the_field(): void
    {
        $response = $this->get('/register');

        // The confirmation feedback is wired to the field it refers to, and
        // it is announced rather than only being drawn.
        $response->assertSee('aria-describedby="password_confirmation-status"', false);
        $response->assertSee('Passwords do not match.', false);
    }

    public function test_user_can_register_and_is_signed_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ada Debater',
            'email' => 'ada@example.com',
            'password' => 'Sup3rSecretPass',
            'password_confirmation' => 'Sup3rSecretPass',
        ]);

        $response->assertRedirect('/dashboard/overview');

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
        $this->assertAuthenticated();

        $user = User::where('email', 'ada@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('Sup3rSecretPass', $user->password));
    }

    public function test_registration_requires_a_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'password' => 'Sup3rSecretPass',
            'password_confirmation' => 'Sup3rSecretPass',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $this->post('/register', [
            'name' => 'Someone',
            'email' => 'new@example.com',
            'password' => 'Sup3rSecretPass',
            'password_confirmation' => 'Different123',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    // ── Login / logout ─────────────────────────────────────────────────────

    public function test_login_screen_renders(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Welcome back');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $response = $this->post('/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard/overview');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_bad_credentials(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->post('/login', [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_signed_in_users_are_redirected_away_from_login_and_register(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/login')
            ->assertRedirect('/dashboard/overview');

        $this->actingAs(User::factory()->create())
            ->get('/register')
            ->assertRedirect('/dashboard/overview');
    }

    // ── Account settings page ──────────────────────────────────────────────

    public function test_account_page_requires_authentication(): void
    {
        $this->get('/dashboard/settings')->assertRedirect('/login');
    }

    public function test_account_page_renders_for_signed_in_user(): void
    {
        $user = User::factory()->create(['name' => 'Ada Debater']);

        $response = $this->actingAs($user)->get('/dashboard/settings');

        $response->assertStatus(200);
        $response->assertSee('Ada Debater');
        $response->assertSee($user->email);
        $response->assertSee('Profile');
        $response->assertSee('Password');
    }

    public function test_the_settings_page_carries_no_statistics(): void
    {
        // The settings page is a form. It used to open with a strip of
        // scorecards — the number of debates, the number of reports, the
        // average score — which was a copy of what the dashboard already
        // says. A settings page that reports your statistics asks to be
        // read as a report, and the copy can only ever be out of date
        // relative to the page that owns it.
        //
        // The practice overview lives on the dashboard, and only there.
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/dashboard/settings')->getContent();

        $this->assertStringNotContainsString('surf-stat', $html);
        $this->assertStringNotContainsString('Avg Score', $html);
        $this->assertStringNotContainsString('Best Score', $html);

        // The dashboard still has its own numbers — the rule is about
        // where they live, not about removing them.
        $dashboard = $this->actingAs($user)->get('/dashboard/overview')->getContent();

        $this->assertStringContainsString('surf-stat', $dashboard);
    }

    public function test_the_settings_page_is_organised_into_named_sections(): void
    {
        // Each thing that can be changed about the account gets its own
        // titled section. A reader can find the part they need by
        // reading the headings, without a list of them beside the form.
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/dashboard/settings')->getContent();

        foreach (['profile', 'password', 'danger-zone'] as $section) {
            $this->assertStringContainsString('id="'.$section.'"', $html);
        }

        // The page does not carry a second, redundant list of its own
        // sections — the headings are the navigation.
        $this->assertStringNotContainsString('surf-settings-nav', $html);
        $this->assertStringNotContainsString('href="#profile"', $html);
    }

    /**
     * Every aria-describedby on the page points at an element that exists.
     *
     * A describedby that resolves to nothing is worse than no describedby
     * at all: a screen reader reports it as a broken reference and says
     * nothing about the field. It is also silent in the HTML, so nothing
     * else catches it.
     */
    public function test_every_describedby_reference_on_the_settings_page_resolves(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/dashboard/settings')->getContent();

        preg_match_all('/aria-describedby="([^"]+)"/', $html, $matches);

        $this->assertNotEmpty($matches[1], 'The settings page should describe at least one field.');

        foreach ($matches[1] as $describedby) {
            foreach (preg_split('/\s+/', trim($describedby)) as $id) {
                $this->assertStringContainsString(
                    'id="'.$id.'"',
                    $html,
                    "aria-describedby points at #{$id}, but no such element exists on the page.",
                );
            }
        }
    }

    /**
     * The same check on the credential screens.
     */
    public function test_every_describedby_reference_on_the_credential_screens_resolves(): void
    {
        foreach (['/login', '/register'] as $path) {
            $html = $this->get($path)->getContent();

            preg_match_all('/aria-describedby="([^"]+)"/', $html, $matches);

            foreach ($matches[1] as $describedby) {
                foreach (preg_split('/\s+/', trim($describedby)) as $id) {
                    $this->assertStringContainsString(
                        'id="'.$id.'"',
                        $html,
                        "aria-describedby on {$path} points at #{$id}, but no such element exists.",
                    );
                }
            }
        }
    }

    public function test_the_settings_fields_use_the_same_form_family_as_the_credential_screens(): void
    {
        // The sign-in form and the settings form must be the same
        // control. Two similar-looking inputs drift apart: one gets a
        // new focus ring, the other loses its icon, and the person who
        // signed in on one screen meets a stranger on the next.
        $user = User::factory()->create();

        // The sign-in page is fetched first: a signed-in visitor is
        // redirected away from it, so it has to be read while still a
        // guest.
        $login = $this->get('/login')->getContent();
        $settings = $this->actingAs($user)->get('/dashboard/settings')->getContent();

        foreach (['form-input', 'form-label', 'form-input-wrap', 'form-input-icon', 'form-reveal'] as $class) {
            $this->assertStringContainsString($class, $settings, "The settings page should use {$class}.");
            $this->assertStringContainsString($class, $login, "The sign-in page should use {$class}.");
        }

        // The settings page must not carry a lookalike input of its own.
        $this->assertStringNotContainsString('surf-input', $settings);
    }

    // ── Overview (dashboard) page ─────────────────────────────────────────

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard/overview')->assertRedirect('/login');
    }

    public function test_dashboard_renders_for_signed_in_user(): void
    {
        $user = User::factory()->create(['name' => 'Ada Debater']);

        $response = $this->actingAs($user)->get('/dashboard/overview');

        $response->assertStatus(200);
        $response->assertSee('Overview');
        $response->assertSee('Ada');
        $response->assertSee('Recent Debates');
    }

    public function test_dashboard_shows_the_users_own_debates(): void
    {
        $user = User::factory()->create();
        $debate = $this->createDebateFor($user, sessionId: 'viewer-session');

        $response = $this->actingAs($user)->get('/dashboard/overview');

        $response->assertStatus(200);
        $response->assertSee($debate->motion->textFor($debate->language));
    }

    public function test_dashboard_does_not_leak_other_users_debates(): void
    {
        $other = User::factory()->create();
        $this->createDebateFor($other, sessionId: 'another-users-session');

        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get('/dashboard/overview');

        $response->assertStatus(200);
        $response->assertDontSee('This House believes another user owns this.');
    }

    public function test_the_history_page_of_a_new_account_is_empty(): void
    {
        $this->seed(DatabaseSeeder::class);

        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get('/dashboard/history');

        $response->assertStatus(200);

        // The seeded demo debate belongs to the demo account. It is not
        // pushed into anybody else's record, so a newly registered
        // account opens on an empty ledger and fills it by debating.
        $seedDebate = Debate::where('session_id', config('debate.seed_session_id'))->first();
        $response->assertDontSee(route('debates.feedback', $seedDebate->id), false);

        // And the page says so, rather than showing an empty table.
        $response->assertSee('No sessions on record');
    }

    public function test_dashboard_lists_an_unfinished_debate_in_the_recent_table(): void
    {
        $user = User::factory()->create();
        $debate = $this->createDebateFor($user, sessionId: 'viewer-session');

        $response = $this->actingAs($user)->get('/dashboard/overview');

        $response->assertStatus(200);

        // The recent table is the account's ledger: every debate appears
        // in it, including the one that has no score yet. The table's
        // header claims to link to all of them, so it has to actually
        // list them.
        $motion = $debate->motion->textFor($debate->language);

        $this->assertStringContainsString(
            $motion,
            $this->panelMarkup($response->getContent(), 'Recent Debates'),
            'The recent table should list the unfinished debate.',
        );
    }

    public function test_dashboard_does_not_repeat_the_same_debate_within_one_surface(): void
    {
        $user = User::factory()->create();
        $debate = $this->createDebateFor($user, sessionId: 'viewer-session');

        $response = $this->actingAs($user)->get('/dashboard/overview');

        $response->assertStatus(200);

        // Within a single surface a debate appears once. Two copies of
        // the same motion in the same panel would read as a duplicated
        // page rather than a dashboard.
        $motion = $debate->motion->textFor($debate->language);

        $this->assertSame(
            1,
            substr_count($this->panelMarkup($response->getContent(), 'Recent Debates'), $motion),
            'The recent table should mention the debate exactly once.',
        );
    }

    public function test_dashboard_shows_only_the_four_most_recent_debates(): void
    {
        $user = User::factory()->create();

        // Six debates, so the fifth and sixth fall off the end.
        for ($i = 0; $i < 6; $i++) {
            $this->createDebateFor($user, sessionId: 'recent-'.$i);
        }

        $html = $this->actingAs($user)->get('/dashboard/overview')->getContent();

        $rows = substr_count($this->panelMarkup($html, 'Recent Debates'), 'surf-cell-primary');

        $this->assertSame(
            4,
            $rows,
            'The overview should list the four most recent debates, not every one.',
        );
    }

    public function test_a_scored_debate_links_to_its_report(): void
    {
        $this->seed(DatabaseSeeder::class);

        // The seeded account owns the seeded debate, so its own report
        // link appears on its dashboard.
        $owner = User::where('email', config('debate.seed_account_email'))->first();
        $seedDebate = Debate::where('session_id', config('debate.seed_session_id'))->first();

        $response = $this->actingAs($owner)->get('/dashboard/overview');

        $response->assertStatus(200);

        // A scored debate opens its report; the score cell is the link
        // the reader follows to read what the adjudicator said.
        $response->assertSee(route('debates.feedback', $seedDebate->id), false);
    }

    public function test_dashboard_shows_the_rubric_breakdown_for_reports(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', config('debate.seed_account_email'))->first();

        $response = $this->actingAs($owner)->get('/dashboard/overview');

        $response->assertStatus(200);
        $response->assertSee('Rubric Averages');
        $response->assertSee('Matter');
        $response->assertSee('Manner');
        $response->assertSee('Method');
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)
            ->patch('/dashboard/settings', [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ])
            ->assertRedirect('/dashboard/settings');

        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals('new@example.com', $user->email);
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/dashboard/settings/password', [
                'current_password' => 'password',
                'password' => 'BrandNewPass123',
                'password_confirmation' => 'BrandNewPass123',
            ])
            ->assertRedirect('/dashboard/settings');

        $this->assertTrue(Hash::check('BrandNewPass123', $user->fresh()->password));
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/dashboard/settings/password', [
                'current_password' => 'not-the-password',
                'password' => 'BrandNewPass123',
                'password_confirmation' => 'BrandNewPass123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_change_enforces_the_password_policy(): void
    {
        $user = User::factory()->create();

        // Too short, no upper case, no digit — the same rule registration
        // applies. A weak password must never be accepted here either.
        $this->actingAs($user)
            ->patch('/dashboard/settings/password', [
                'current_password' => 'password',
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_the_password_hint_on_screen_matches_the_enforced_policy(): void
    {
        // The hint is the only place the user learns what the policy is.
        // If it drifts from the rule the validator actually enforces, users
        // are told to type something that will be rejected.
        $policy = 'At least 10 characters, with an upper case letter, a lower case letter and a digit.';

        $this->get('/register')->assertSee($policy);
        $this->actingAs(User::factory()->create())->get('/dashboard/settings')->assertSee($policy);

        // And the hint must not describe the old, looser rule.
        $this->get('/register')->assertDontSee('At least 8 characters');
    }

    public function test_user_can_delete_account_and_their_debates(): void
    {
        $user = User::factory()->create();
        $debate = $this->createDebateFor($user);

        $this->actingAs($user)
            ->delete('/dashboard/settings')
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('debates', ['id' => $debate->id]);
    }

    // ── Debate ownership ───────────────────────────────────────────────────

    public function test_debates_created_by_a_signed_in_user_are_owned_by_that_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/debates', [
            'motion_text' => 'This House believes social media does more harm than good.',
            'user_side' => 'government',
            'persona_id' => Persona::first()->id,
            'difficulty' => 'intermediate',
            'mode' => 'tournament',
            'language' => 'en',
        ]);

        $debate = Debate::first();
        $this->assertNotNull($debate);
        $this->assertEquals($user->id, $debate->user_id);
    }

    public function test_a_guest_cannot_create_a_debate(): void
    {
        // Debates are only created by an account, so there are no "orphan"
        // guest debates left lying around to be claimed later.
        $this->post('/debates', [
            'motion_text' => 'This House believes guest debates carry over.',
            'user_side' => 'government',
            'persona_id' => Persona::first()->id,
            'difficulty' => 'intermediate',
            'mode' => 'tournament',
            'language' => 'en',
        ])->assertRedirect('/login');

        $this->assertDatabaseCount('debates', 0);
    }

    public function test_signed_in_user_history_includes_their_debates_from_other_sessions(): void
    {
        $user = User::factory()->create();

        // A debate created in a *different* session (e.g. another device).
        $debate = $this->createDebateFor($user, sessionId: 'some-other-session');

        $response = $this->actingAs($user)->get('/dashboard/history');

        $response->assertStatus(200);
        $response->assertSee($debate->motion->textFor($debate->language));
    }

    public function test_history_does_not_leak_other_users_debates(): void
    {
        $other = User::factory()->create();
        $this->createDebateFor($other, sessionId: 'another-users-session');

        $viewer = User::factory()->create();

        $response = $this->actingAs($viewer)->get('/dashboard/history');

        $response->assertStatus(200);
        $response->assertDontSee('This House believes another user owns this.');
    }

    public function test_user_cannot_delete_a_debate_owned_by_someone_else(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $debate = $this->createDebateFor($owner, sessionId: 'owner-session');

        $this->actingAs($other)
            ->delete('/debates/'.$debate->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('debates', ['id' => $debate->id]);
    }

    public function test_guest_cannot_delete_the_seeded_demo_debate(): void
    {
        $this->seed(DatabaseSeeder::class);

        $seedDebate = Debate::where('session_id', config('debate.seed_session_id'))->first();
        $this->assertNotNull($seedDebate);

        // A guest visitor must not be able to remove the demo debate.
        $this->delete('/debates/'.$seedDebate->id)->assertRedirect('/login');

        $this->assertDatabaseHas('debates', ['id' => $seedDebate->id]);
    }

    public function test_other_account_cannot_delete_the_seeded_demo_debate(): void
    {
        $this->seed(DatabaseSeeder::class);

        $seedDebate = Debate::where('session_id', config('debate.seed_session_id'))->first();
        $this->assertNotNull($seedDebate);

        $other = User::factory()->create();

        $this->actingAs($other)
            ->delete('/debates/'.$seedDebate->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('debates', ['id' => $seedDebate->id]);
    }

    public function test_seeded_account_owner_can_delete_the_seeded_demo_debate(): void
    {
        $this->seed(DatabaseSeeder::class);

        $seedDebate = Debate::where('session_id', config('debate.seed_session_id'))->first();
        $owner = User::where('email', config('debate.seed_account_email'))->first();

        $this->actingAs($owner)
            ->delete('/debates/'.$seedDebate->id)
            ->assertRedirect('/dashboard/history');

        $this->assertDatabaseMissing('debates', ['id' => $seedDebate->id]);
    }

    public function test_history_shows_delete_only_on_debates_the_viewer_owns(): void
    {
        $this->seed(DatabaseSeeder::class);

        // The viewer owns one debate of their own...
        $viewer = User::factory()->create();
        $ownDebate = $this->createDebateFor($viewer, sessionId: 'viewer-session');

        // ...and someone else owns the seeded demo debate.
        $seedDebate = Debate::where('session_id', config('debate.seed_session_id'))->first();
        $this->assertNotNull($seedDebate);

        $response = $this->actingAs($viewer)->get('/dashboard/history');

        $response->assertStatus(200);

        // Only the viewer's own debate is listed, and so only it carries
        // a delete button. The seeded demo debate belongs to the demo
        // account and never reaches this list.
        $this->assertSame(1, substr_count($response->getContent(), 'title="Delete session"'));
        $response->assertSee('/debates/'.$ownDebate->id, false);
        $response->assertDontSee('/debates/'.$seedDebate->id, false);
    }

    public function test_history_shows_the_delete_button_on_the_seeded_debate_for_its_owner(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', config('debate.seed_account_email'))->first();

        $response = $this->actingAs($owner)->get('/dashboard/history');

        $response->assertStatus(200);
        $this->assertSame(1, substr_count($response->getContent(), 'title="Delete session"'));
    }

    // ── Seeded account ─────────────────────────────────────────────────────

    public function test_seeded_account_exists_and_owns_the_seeded_debate_with_its_report(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', config('debate.seed_account_email'))->first();

        $this->assertNotNull($user, 'The seeded account was not created.');
        $this->assertTrue(
            Hash::check(config('debate.seed_account_password'), $user->password),
            'The seeded account password does not match the configured one.'
        );

        $seedDebate = Debate::where('session_id', config('debate.seed_session_id'))->first();

        $this->assertNotNull($seedDebate, 'The seeded debate was not created.');
        $this->assertEquals($user->id, $seedDebate->user_id, 'The seeded account does not own the seeded debate.');
        $this->assertNotNull($seedDebate->adjudication, 'The seeded debate has no adjudication report.');
        $this->assertEquals(3, $seedDebate->rounds()->count());
        $this->assertEquals(6, $seedDebate->allTurns()->count());
    }

    public function test_seeded_account_can_sign_in_and_see_its_debate_and_report(): void
    {
        $this->seed(DatabaseSeeder::class);

        $response = $this->post('/login', [
            'email' => config('debate.seed_account_email'),
            'password' => config('debate.seed_account_password'),
        ]);

        $response->assertRedirect('/dashboard/overview');
        $this->assertAuthenticated();

        $dashboard = $this->get('/dashboard/overview');
        $dashboard->assertStatus(200);
        $dashboard->assertSee('Recent Debates');
        // The account owns the seeded debate, so it appears in its own
        // recent list and its score is plotted on the trend chart.
        $dashboard->assertSee('Score Trend');
        $dashboard->assertSee('Rubric Averages');

        $seedDebate = Debate::where('session_id', config('debate.seed_session_id'))->first();
        $this->assertEquals($user = User::where('email', config('debate.seed_account_email'))->first()->id, $seedDebate->user_id);

        // The seeded report is reachable.
        $this->get('/debates/'.$seedDebate->id.'/feedback')->assertStatus(200);
    }

    public function test_seeders_are_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertEquals(1, User::where('email', config('debate.seed_account_email'))->count());
        $this->assertEquals(1, Debate::where('session_id', config('debate.seed_session_id'))->count());
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function createDebateFor(User $user, string $sessionId = 'test-session'): Debate
    {
        $motion = Motion::create([
            'text_en' => 'This House believes another user owns this.',
            'text_bn' => null,
            'source' => 'manual',
        ]);

        return Debate::create([
            'session_id' => $sessionId,
            'user_id' => $user->id,
            'motion_id' => $motion->id,
            'user_side' => 'government',
            'persona_id' => Persona::first()->id,
            'difficulty' => 'intermediate',
            'mode' => 'tournament',
            'language' => 'en',
            'status' => 'in_progress',
        ]);
    }

    /**
     * The markup of the panel whose heading carries the given title.
     *
     * The dashboard repeats a debate's motion across several surfaces —
     * the resume callout, the recent table, the latest-report panel.
     * A page-wide count cannot tell those apart, so the assertions
     * scope themselves to one panel at a time.
     */
    private function panelMarkup(string $html, string $title): string
    {
        $start = strpos($html, $title);

        if ($start === false) {
            return '';
        }

        // Walk back to the panel that owns the heading. The match has to
        // be the panel wrapper itself — "surf-panel" also prefixes
        // "surf-panel-head" and "surf-panel-body".
        $panelStart = strrpos(
            substr($html, 0, $start),
            '<div class="surf-panel"',
        );

        if ($panelStart === false) {
            return '';
        }

        // Walk forward to that panel's own closing tag. The nesting is
        // counted so the panel body's own divs do not end the search.
        $depth = 0;
        $cursor = $panelStart;

        while ($cursor < strlen($html)) {
            $open = strpos($html, '<div', $cursor);
            $close = strpos($html, '</div>', $cursor);

            if ($close === false) {
                return '';
            }

            if ($open !== false && $open < $close) {
                $depth++;
                $cursor = $open + 4;

                continue;
            }

            $depth--;

            if ($depth === 0) {
                return substr($html, $panelStart, $close - $panelStart + 6);
            }

            $cursor = $close + 6;
        }

        return '';
    }
}
