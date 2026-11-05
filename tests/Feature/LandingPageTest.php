<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Landing page tests.
 *
 * The landing page is the only public page, so its footer is the first
 * thing a visitor reads. These tests lock in two decisions:
 *
 *   1. The footer's section links point at sections that exist on the page,
 *      so none of them is a dead link.
 *   2. The footer of a public page does not advertise pages that require an
 *      account. A visitor who clicks "Dashboard" and is bounced to a sign-in
 *      form has been sent somewhere they cannot go.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    // ── Footer links ──────────────────────────────────────────────────────

    public function test_the_footer_links_only_to_sections_that_exist(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('href="#features"', false);
        $response->assertSee('href="#how-it-works"', false);
        $response->assertSee('href="#scoring"', false);

        // And those sections are actually on the page, so the anchors land.
        $response->assertSee('id="features"', false);
        $response->assertSee('id="how-it-works"', false);
        $response->assertSee('id="scoring"', false);
    }

    public function test_the_footer_of_a_public_page_does_not_link_to_protected_pages(): void
    {
        $response = $this->get('/');

        // A guest has no dashboard, no history and no settings to go to.
        $response->assertDontSee(route('dashboard'), false);
        $response->assertDontSee(route('history.index'), false);
        $response->assertDontSee(route('account.index'), false);
        $response->assertDontSee(route('setup'), false);

        // It does offer the way in.
        $response->assertSee(route('login'), false);
        $response->assertSee(route('register'), false);
    }

    public function test_the_footer_of_a_signed_in_page_offers_the_account_pages(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertSee(route('dashboard'), false);
        $response->assertSee(route('account.index'), false);

        // And the way in is no longer offered to someone already inside.
        $response->assertDontSee(route('register'), false);
    }

    // ── Page structure ────────────────────────────────────────────────────

    public function test_the_call_to_action_lives_in_the_nav_and_not_in_the_footer(): void
    {
        $guest = $this->get('/');

        // A guest's way in is the nav button, not a second one in the footer.
        $guest->assertSee('app-nav-actions', false);
        $guest->assertSee(route('login'), false);
        $guest->assertSee(route('register'), false);
        $guest->assertDontSee('landing-footer-cta', false);

        $user = User::factory()->create();

        $signedIn = $this->actingAs($user)->get('/');

        // Signed in, the "Go to Dashboard" button replaces the
        // sign-in/register buttons — the person is already inside, and the
        // button is the way back into the product.
        $signedIn->assertSee('Go to Dashboard', false);
        $signedIn->assertSee(route('dashboard'), false);
        $signedIn->assertDontSee(route('login'), false);
        $signedIn->assertDontSee('landing-footer-cta', false);
    }

    public function test_a_signed_in_visitor_gets_only_the_dashboard_button_in_the_nav(): void
    {
        $signedIn = $this->actingAs(User::factory()->create())->get('/');

        // One way back into the app, and it says so outright. The account
        // pill that used to sit here was a popover of five links — a menu
        // to open before you could even reach the dashboard — and it
        // repeated the destinations the rail already lists on every working
        // page.
        $signedIn->assertSee('Go to Dashboard', false);
        $signedIn->assertDontSee('app-account-trigger', false);
        $signedIn->assertDontSee('Get Started', false);
        $signedIn->assertDontSee('nav-start-practice', false);
        $signedIn->assertDontSee('nav-history-link', false);
    }

    public function test_the_landing_page_nav_does_not_list_the_dashboard_pages(): void
    {
        $signedIn = $this->actingAs(User::factory()->create())->get('/');

        // The landing page is a marketing surface. Its nav keeps the logo
        // and one button back into the product, but the individual
        // dashboard links stay out of it — the rail carries them on every
        // working page, so a bar of links here would repeat them.
        $nav = $this->navBlock($signedIn->getContent());

        $this->assertNotNull($nav, 'The landing page should still render a nav bar.');
        $this->assertStringNotContainsString('app-nav-link', $nav);
        $this->assertStringNotContainsString('app-nav-links', $nav);
        $this->assertStringNotContainsString('app-account-trigger', $nav);
        $this->assertStringContainsString('app-nav-dashboard', $nav);

        foreach (['Dashboard', 'New Debate', 'History', 'Settings'] as $label) {
            $this->assertStringNotContainsString(">{$label}\n", $nav);
        }
    }

    public function test_the_dashboard_nav_still_lists_the_four_main_pages(): void
    {
        // The landing page is the only page that drops these links. Every
        // working page keeps them, so a signed-in user can always reach
        // them without opening the account menu.
        $dashboard = $this->actingAs(User::factory()->create())->get('/dashboard/overview');

        $nav = $this->sidebarBlock($dashboard->getContent());

        $this->assertNotNull($nav, 'The dashboard should render a sidebar.');
        $this->assertStringContainsString('app-side-link', $nav);

        // Assert the four destinations by the route they point at, rather
        // than by the class their row wears. The class is a styling
        // detail that the rail is free to change; the destinations are
        // the contract.
        foreach ([
            '/dashboard/overview' => 'Dashboard',
            '/dashboard/setup' => 'New Debate',
            '/dashboard/history' => 'History',
            '/dashboard/settings' => 'Settings',
        ] as $url => $label) {
            $this->assertStringContainsString(
                'href="'.url($url).'"',
                $nav,
                "The rail should link to {$label}.",
            );
        }

        // And the sign-out row is not one of those destinations, so it
        // does not inflate the count. "New Debate" is the rail's primary
        // action rather than a plain destination row, so it is counted
        // separately: together they are the four destinations.
        $destinationRows = preg_match_all('/class="app-side-link\b(?![^"]*is-danger)/', $nav);
        $primaryActions = preg_match_all('/class="[^"]*\bapp-side-cta\b/', $nav);

        $this->assertSame(3, $destinationRows);
        $this->assertSame(1, $primaryActions);
        $this->assertSame(4, $destinationRows + $primaryActions);
    }

    /**
     * Pull the <nav> element out of a rendered page.
     */
    private function navBlock(string $html): ?string
    {
        return preg_match('/<nav class="app-nav".*?<\/nav>/s', $html, $m) ? $m[0] : null;
    }

    /**
     * Pull the sidebar's navigation element out of a rendered page.
     *
     * The signed-in workspace is reached through a sidebar rather than
     * the horizontal bar the landing page keeps.
     */
    private function sidebarBlock(string $html): ?string
    {
        return preg_match('/<nav class="app-side-nav".*?<\/nav>/s', $html, $m) ? $m[0] : null;
    }

    public function test_the_landing_page_has_exactly_one_h1(): void
    {
        // A page with two h1s has no single subject, which breaks both the
        // document outline and the page's heading for a screen reader.
        $response = $this->get('/');

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    // ── Hackathon badge ───────────────────────────────────────────────────

    public function test_the_hackathon_badge_sits_in_the_hero_and_not_in_the_nav(): void
    {
        $response = $this->get('/');

        $response->assertSee('landing-badge', false);
        $response->assertSee('Built-for-AIgnite-2026.png', false);

        // It is a hero credit, not navigation chrome. It must not creep into
        // the fixed nav bar or into the scrolling marquee.
        $this->assertStringNotContainsString(
            'Built-for-AIgnite-2026.png',
            $this->extractNavMarkup($response->getContent()),
        );
        $this->assertStringNotContainsString(
            'Built-for-AIgnite-2026.png',
            $this->extractMarqueeMarkup($response->getContent()),
        );
    }

    public function test_the_hackathon_badge_describes_itself_for_assistive_technology(): void
    {
        $response = $this->get('/');

        // The badge's own small print is unreadable at display size, so the
        // alt text has to carry the full claim for a screen reader.
        $response->assertSee('alt="Built for AIgnite Buildathon 2026 — Ostad presents, National AI Hackathon for B2C Solutions"', false);
    }

    public function test_the_badge_is_stacked_above_the_eyebrow_and_not_beside_it(): void
    {
        $html = $this->get('/')->getContent();

        // Scoped to the provenance block: the same words appear in the
        // page's meta description, so a page-wide search finds the wrong one.
        $block = $this->extractElement($html, 'div', 'landing-hero-provenance');

        $this->assertNotSame('', $block, 'The provenance block was not found on the page.');

        $badge = strpos($block, 'landing-badge');
        $eyebrow = strpos($block, 'landing-eyebrow');

        $this->assertNotFalse($badge);
        $this->assertNotFalse($eyebrow);

        // The badge is the credential and reads first; the eyebrow labels
        // the product below it. Set on one line they crowd each other.
        $this->assertLessThan(
            $eyebrow,
            $badge,
            'The badge should be rendered above the eyebrow, not beside it.',
        );
    }

    public function test_the_landing_page_footer_is_labelled_as_a_landmark(): void
    {
        $response = $this->get('/');

        $response->assertSee('<footer', false);
        $response->assertSee('aria-label="Explore"', false);
        $response->assertSee('aria-label="Account"', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * The markup of the first <nav> element on the page — the site nav.
     */
    private function extractNavMarkup(string $html): string
    {
        return $this->extractElement($html, 'nav', 'nav-actions');
    }

    /**
     * The markup of the scrolling marquee strip.
     */
    private function extractMarqueeMarkup(string $html): string
    {
        return $this->extractElement($html, 'div', 'landing-marquee-wrap');
    }

    /**
     * Slice out the markup of the first <$tag> that carries the given class.
     */
    private function extractElement(string $html, string $tag, string $class): string
    {
        $start = strpos($html, '<'.$tag.' class="'.$class.'"');

        if ($start === false) {
            // The class may not be the first attribute; fall back to a scan.
            $pattern = '/<'.$tag.'\b[^>]*\bclass="[^"]*\b'.preg_quote($class, '/').'\b[^"]*"[^>]*>/';
            if (! preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
                return '';
            }
            $start = $m[0][1];
        }

        $end = strpos($html, '</'.$tag.'>', $start);

        return $end === false ? '' : substr($html, $start, $end - $start);
    }
}
