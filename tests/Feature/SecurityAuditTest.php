<?php

namespace Tests\Feature;

use App\Models\Debate;
use App\Models\Motion;
use App\Models\Persona;
use App\Models\Round;
use App\Models\Turn;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PersonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Security regression tests.
 *
 * Each test locks in one hardening decision, so that a future change cannot
 * quietly reopen the gap:
 *
 *   1. Only the landing page, login and register are public.
 *   2. A signed-in user can only reach debates their account owns.
 *   3. The seeded demo debate belongs to the demo account alone.
 *   4. AI-spending endpoints are rate limited.
 *   5. Brute-force sign-in attempts lock the account.
 */
class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PersonaSeeder::class);
    }

    // ── 1. Public surface ────────────────────────────────────────────────

    public function test_the_landing_page_is_public(): void
    {
        $this->get('/')->assertStatus(200);
    }

    public function test_the_login_page_is_public(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_the_register_page_is_public(): void
    {
        $this->get('/register')->assertStatus(200);
    }

    public function test_the_health_check_is_public(): void
    {
        $this->get('/healthz')->assertStatus(200);
    }

    // ── 1b. Everything else requires an account ───────────────────────────

    public function test_guests_are_redirected_away_from_every_dashboard_page(): void
    {
        $debate = $this->makeDebate();

        $protected = [
            '/dashboard/overview',
            '/dashboard/setup',
            '/dashboard/history',
            '/dashboard/settings',
            '/debates/'.$debate->id,
            '/debates/'.$debate->id.'/feedback',
            '/debates/'.$debate->id.'/transcript',
        ];

        foreach ($protected as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_guests_are_redirected_away_from_every_mutating_endpoint(): void
    {
        $debate = $this->makeDebate();
        $turn = $this->makeTurn($debate);

        $response = $this->post('/motions/generate', ['language' => 'en']);
        $response->assertRedirect('/login');

        $response = $this->post('/debates', [
            'motion_text' => 'This House believes guests should not reach this.',
            'user_side' => 'government',
            'persona_id' => Persona::first()->id,
            'difficulty' => 'intermediate',
            'mode' => 'tournament',
            'language' => 'en',
        ]);
        $response->assertRedirect('/login');

        $this->post('/debates/'.$debate->id.'/turns', ['phase' => 'opening'])
            ->assertRedirect('/login');

        $this->post('/debates/'.$debate->id.'/turns/'.$turn->id.'/rewrite')
            ->assertRedirect('/login');

        $this->post('/debates/'.$debate->id.'/adjudicate')
            ->assertRedirect('/login');

        $this->delete('/debates/'.$debate->id)
            ->assertRedirect('/login');
    }

    public function test_a_guest_is_redirected_to_the_sign_in_screen(): void
    {
        $this->get('/dashboard/setup')->assertRedirect(route('login'));
    }

    public function test_the_intended_url_is_remembered_so_deep_links_survive_sign_in(): void
    {
        $this->get('/dashboard/setup')->assertRedirect('/login');

        // The login screen must carry the intended destination forward.
        $response = $this->get('/login');
        $response->assertStatus(200);
        $this->assertStringEndsWith('/dashboard/setup', (string) session('url.intended'));
    }

    // ── 2. Ownership (IDOR) ───────────────────────────────────────────────

    public function test_a_user_cannot_open_another_users_debate(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $debate = $this->makeDebate($owner);

        $this->actingAs($other)
            ->get('/debates/'.$debate->id)
            ->assertStatus(403);
    }

    public function test_a_user_cannot_read_another_users_transcript(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $debate = $this->makeDebate($owner);

        $this->actingAs($other)
            ->get('/debates/'.$debate->id.'/transcript')
            ->assertStatus(403);
    }

    public function test_a_user_cannot_read_another_users_report(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $debate = $this->makeDebate($owner);

        $this->actingAs($other)
            ->get('/debates/'.$debate->id.'/feedback')
            ->assertStatus(403);
    }

    public function test_a_user_cannot_submit_a_turn_to_another_users_debate(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $debate = $this->makeDebate($owner);

        $this->actingAs($other)
            ->post('/debates/'.$debate->id.'/turns', ['phase' => 'opening'])
            ->assertStatus(403);
    }

    public function test_a_user_cannot_rewrite_another_users_turn(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $debate = $this->makeDebate($owner);
        $turn = $this->makeTurn($debate);

        $this->actingAs($other)
            ->post('/debates/'.$debate->id.'/turns/'.$turn->id.'/rewrite')
            ->assertStatus(403);
    }

    public function test_a_user_cannot_adjudicate_another_users_debate(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $debate = $this->makeDebate($owner);

        $this->actingAs($other)
            ->post('/debates/'.$debate->id.'/adjudicate')
            ->assertStatus(403);
    }

    public function test_a_user_cannot_delete_another_users_debate(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $debate = $this->makeDebate($owner);

        $this->actingAs($other)
            ->delete('/debates/'.$debate->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('debates', ['id' => $debate->id]);
    }

    public function test_the_owner_can_still_use_their_own_debate(): void
    {
        $owner = User::factory()->create();

        $debate = $this->makeDebate($owner);

        $this->actingAs($owner)->get('/debates/'.$debate->id)->assertStatus(200);
        $this->actingAs($owner)->get('/debates/'.$debate->id.'/transcript')->assertStatus(200);
    }

    // ── 2b. The turn must belong to the debate in the URL ─────────────────

    public function test_a_turn_cannot_be_rewritten_through_another_debates_url(): void
    {
        $owner = User::factory()->create();

        $debateA = $this->makeDebate($owner, 'Motion A');
        $debateB = $this->makeDebate($owner, 'Motion B');

        $turnFromA = $this->makeTurn($debateA);

        // The turn belongs to debate A, but the URL claims debate B.
        $this->actingAs($owner)
            ->post('/debates/'.$debateB->id.'/turns/'.$turnFromA->id.'/rewrite')
            ->assertStatus(404);
    }

    // ── 3. The seeded demo debate belongs to the demo account alone ───────

    public function test_the_seeded_demo_debate_is_not_readable_by_another_account(): void
    {
        $this->seed(DatabaseSeeder::class);

        $seedDebate = Debate::where('session_id', config('debate.seed_session_id'))->first();
        $other = User::factory()->create();

        // The demo debate is a record of the demo account's own practice.
        // Another account may not read it, write to it, or remove it.
        $this->actingAs($other)
            ->get('/debates/'.$seedDebate->id)
            ->assertStatus(403);
    }

    public function test_the_seeded_demo_debate_is_not_writable_by_another_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $seedDebate = Debate::where('session_id', config('debate.seed_session_id'))->first();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->post('/debates/'.$seedDebate->id.'/turns', ['phase' => 'opening'])
            ->assertStatus(403);

        $this->actingAs($other)
            ->post('/debates/'.$seedDebate->id.'/adjudicate')
            ->assertStatus(403);

        $this->actingAs($other)
            ->delete('/debates/'.$seedDebate->id)
            ->assertStatus(403);
    }

    // ── 4. Rate limiting ──────────────────────────────────────────────────

    public function test_a_spray_of_sign_ins_from_one_host_is_throttled(): void
    {
        // One attempt per address, all from the same host. The per-account
        // lockout never trips (each address is used once), so what stops this
        // is the route-level per-IP ceiling.
        for ($i = 0; $i < 20; $i++) {
            $this->post('/login', [
                'email' => "spray{$i}@example.com",
                'password' => 'wrong',
            ])->assertSessionHasErrors('email');
        }

        // The next attempt is rejected by the throttle, not by the credential
        // check — no error bag, just a 429.
        $response = $this->post('/login', [
            'email' => 'spray@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
    }

    public function test_the_account_lockout_takes_precedence_over_the_route_throttle(): void
    {
        // The lockout (5 failures for one account) sits BELOW the route
        // throttle (12/min for one address + IP), so a targeted attack on a
        // single account is answered with a clear lockout message rather
        // than an opaque 429.
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong',
            ])->assertSessionHasErrors('email');
        }

        // The fifth failure trips the lockout and says so plainly.
        $response = $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'locked',
            session('errors')->getBag('default')->first('email')
        );

        // Every later attempt is refused with the same lockout message —
        // the credential check never even runs.
        $response = $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Too many failed sign-in attempts',
            session('errors')->getBag('default')->first('email')
        );
    }

    public function test_the_sign_in_throttle_is_scoped_per_account(): void
    {
        // Lock out one account...
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'locked@example.com',
                'password' => 'wrong',
            ]);
        }

        // ...and confirm a different account is unaffected.
        $user = User::factory()->create(['email' => 'other@example.com']);

        $this->post('/login', [
            'email' => 'other@example.com',
            'password' => 'password',
        ])->assertRedirect('/dashboard/overview');

        $this->assertAuthenticatedAs($user);
    }

    public function test_account_creation_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [
                'name' => 'Bot',
                'email' => "bot{$i}@example.com",
                'password' => 'Str0ngPassphrase',
                'password_confirmation' => 'Str0ngPassphrase',
            ])->assertRedirect('/dashboard/overview');
        }

        $response = $this->post('/register', [
            'name' => 'Bot',
            'email' => 'bot6@example.com',
            'password' => 'Str0ngPassphrase',
            'password_confirmation' => 'Str0ngPassphrase',
        ]);

        $response->assertStatus(429);
        $this->assertDatabaseMissing('users', ['email' => 'bot6@example.com']);
    }

    // ── 5. Brute-force lockout ────────────────────────────────────────────

    public function test_an_account_is_locked_out_after_repeated_failures(): void
    {
        User::factory()->create(['email' => 'target@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'target@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        // Even the CORRECT password is now refused.
        $this->post('/login', [
            'email' => 'target@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_successful_sign_in_clears_the_failure_counter(): void
    {
        User::factory()->create(['email' => 'target@example.com']);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', [
                'email' => 'target@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/login', [
            'email' => 'target@example.com',
            'password' => 'password',
        ])->assertRedirect('/dashboard/overview');

        // Sign out again so the next attempt is treated as a guest attempt.
        // The failure counter lives in the cache, not the session, so it
        // survives the sign-out — which is exactly what we want to observe.
        $this->post('/logout')->assertRedirect('/');

        // A later failure must not immediately re-lock the account: the
        // counter restarted from zero at the successful sign-in.
        $this->post('/login', [
            'email' => 'target@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'do not match',
            session('errors')->getBag('default')->first('email')
        );
    }

    // ── 6. Password policy ────────────────────────────────────────────────

    public function test_weak_passwords_are_rejected(): void
    {
        $this->post('/register', [
            'name' => 'Weak',
            'email' => 'weak@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_emails_are_normalized_so_case_variants_cannot_duplicate_an_account(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', [
            'name' => 'Impostor',
            'email' => 'TAKEN@Example.com',
            'password' => 'Str0ngPassphrase',
            'password_confirmation' => 'Str0ngPassphrase',
        ])->assertSessionHasErrors('email');

        // No second account was created for the same (case-folded) address.
        $this->assertEquals(1, User::where('email', 'taken@example.com')->count());
        $this->assertEquals(1, User::count());
        $this->assertGuest();
    }

    public function test_registering_normalizes_the_stored_email_address(): void
    {
        $this->post('/register', [
            'name' => 'Mixed Case',
            'email' => '  Mixed.Case@Example.COM ',
            'password' => 'Str0ngPassphrase',
            'password_confirmation' => 'Str0ngPassphrase',
        ])->assertRedirect('/dashboard/overview');

        $this->assertDatabaseHas('users', ['email' => 'mixed.case@example.com']);
    }

    // ── 7. Security headers ───────────────────────────────────────────────

    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    // ── 8. Voice recordings are private ───────────────────────────────────

    public function test_a_guest_cannot_download_a_users_voice_recording(): void
    {
        $owner = User::factory()->create();
        $debate = $this->makeDebate($owner);

        $this->storeAudio($debate, 'user_'.Str::uuid().'.webm');

        $this->get('/debates/'.$debate->id.'/audio/user_x.webm')
            ->assertRedirect('/login');
    }

    public function test_a_user_cannot_download_another_users_voice_recording(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $debate = $this->makeDebate($owner);
        $name = 'user_'.Str::uuid().'.webm';

        $this->storeAudio($debate, $name);

        $this->actingAs($other)
            ->get('/debates/'.$debate->id.'/audio/'.$name)
            ->assertStatus(403);
    }

    public function test_the_owner_can_download_their_own_voice_recording(): void
    {
        $owner = User::factory()->create();
        $debate = $this->makeDebate($owner);
        $name = 'user_'.Str::uuid().'.webm';

        $this->storeAudio($debate, $name);

        $response = $this->actingAs($owner)->get('/debates/'.$debate->id.'/audio/'.$name);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'audio/webm');

        // The response streams the stored file itself.
        $this->assertSame(
            Storage::disk('local')->path('audio/'.$name),
            $response->baseResponse->getFile()->getPathname()
        );
    }

    public function test_a_voice_recording_is_never_cached_by_a_shared_cache(): void
    {
        $owner = User::factory()->create();
        $debate = $this->makeDebate($owner);
        $name = 'user_'.Str::uuid().'.webm';

        $this->storeAudio($debate, $name);

        $response = $this->actingAs($owner)->get('/debates/'.$debate->id.'/audio/'.$name);

        // BinaryFileResponse defaults to `$public = true`, which stamps
        // "Cache-Control: public" over whatever the controller sets. A shared
        // proxy could then keep serving one user's recording after they
        // signed out, so the directive must stay private.
        $cacheControl = $response->baseResponse->headers->get('Cache-Control');

        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringNotContainsString('public', str_replace('public, ', '', $cacheControl));
    }

    public function test_audio_files_are_not_served_off_the_public_disk(): void
    {
        $owner = User::factory()->create();
        $debate = $this->makeDebate($owner);
        $name = 'user_'.Str::uuid().'.webm';

        $this->storeAudio($debate, $name);

        // The private disk is not reachable through the storage route.
        $this->get('/storage/audio/'.$name)->assertStatus(403);
    }

    public function test_the_audio_endpoint_rejects_path_traversal(): void
    {
        $owner = User::factory()->create();
        $debate = $this->makeDebate($owner);

        // Even the owner cannot use the audio endpoint to read other files.
        $this->actingAs($owner)
            ->get('/debates/'.$debate->id.'/audio/..%2F..%2F..%2F.env')
            ->assertStatus(404);
    }

    // ── 9. JSON endpoints answer JSON, and never leak to guests ──────────

    public function test_a_json_request_to_a_validation_failure_answers_with_json(): void
    {
        $user = User::factory()->create();

        // The frontend calls this endpoint with fetch() and reads res.json().
        // If the server answered with an HTML redirect page, res.json() would
        // throw and the user would see a generic failure instead of the real
        // field error.
        $response = $this->actingAs($user)
            ->postJson('/motions/generate', ['language' => 'klingon']);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['language']]);
    }

    public function test_an_ordinary_form_post_still_redirects_back_with_errors(): void
    {
        $user = User::factory()->create();

        // The debate setup form is a normal HTML form post. It must keep the
        // redirect-back-with-errors behaviour that the Blade view relies on.
        $response = $this->actingAs($user)
            ->post('/motions/generate', ['language' => 'klingon']);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('language');
    }

    public function test_a_guest_json_request_is_refused_with_401_not_a_redirect(): void
    {
        // A guest hitting a JSON endpoint must get a 401, not a 302 to the
        // sign-in page — a redirect body is not valid JSON and would break
        // the caller.
        $response = $this->postJson('/motions/generate', ['language' => 'en']);

        $response->assertStatus(401);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Write a fake audio file to the private disk and point a turn at it.
     */
    private function storeAudio(Debate $debate, string $name): void
    {
        Storage::disk('local')->put('audio/'.$name, 'fake-audio-bytes');

        $round = $debate->rounds()->first();

        Turn::create([
            'id' => (string) Str::uuid(),
            'round_id' => $round->id,
            'speaker' => 'user',
            'transcript' => 'A recorded turn.',
            'audio_path' => $name,
        ]);
    }

    private function makeDebate(?User $owner = null, string $motionText = 'This House believes audits matter.'): Debate
    {
        $motion = Motion::create([
            'text_en' => $motionText,
            'text_bn' => null,
            'source' => 'manual',
        ]);

        $debate = Debate::create([
            'session_id' => 'audit-session',
            'user_id' => $owner?->id,
            'motion_id' => $motion->id,
            'user_side' => 'government',
            'persona_id' => Persona::first()->id,
            'difficulty' => 'intermediate',
            'mode' => 'tournament',
            'language' => 'en',
            'status' => 'in_progress',
        ]);

        Round::create([
            'debate_id' => $debate->id,
            'phase' => 'opening',
            'phase_order' => 1,
        ]);

        return $debate;
    }

    private function makeTurn(Debate $debate): Turn
    {
        $round = $debate->rounds()->first();

        return Turn::create([
            'id' => (string) Str::uuid(),
            'round_id' => $round->id,
            'speaker' => 'user',
            'transcript' => 'This is a user argument that should not be reachable by another account.',
            'audio_path' => null,
        ]);
    }
}
