<?php

namespace Tests\Feature;

use App\Models\Adjudication;
use App\Models\Debate;
use App\Models\Motion;
use App\Models\Persona;
use App\Models\Round;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The adjudication report page.
 *
 * The report is a document, and a document has two readers: the debater
 * who has just come off the floor, and the judge who wants to see how
 * the mark was reached. These tests hold the shape of the document in
 * place — that it says what it must say, and that it does not say the
 * same thing twice.
 */
class ReportPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PersonaSeeder::class);
    }

    private function makeDebate(User $user): Debate
    {
        $motion = Motion::create([
            'text_en'  => 'This House believes that open-sourced AI should be regulated.',
            'text_bn'  => 'এই সভা মনে করে যে ওপেন-সোর্সড এআই নিয়ন্ত্রণ করা উচিত।',
            'category' => 'Technology',
            'source'   => 'manual',
        ]);

        $debate = Debate::create([
            'session_id' => 'session-'.uniqid(),
            'user_id'    => $user->id,
            'motion_id'  => $motion->id,
            'persona_id' => Persona::first()->id,
            'user_side'  => 'government',
            'difficulty' => 'advanced',
            'mode'       => 'tournament',
            'language'   => 'en',
            'status'     => 'adjudicated',
        ]);

        $round = Round::create([
            'debate_id'   => $debate->id,
            'phase'       => 'opening',
            'phase_order' => 1,
        ]);

        Turn::create([
            'round_id'   => $round->id,
            'speaker'    => 'user',
            'transcript' => 'We should regulate the deployment of general-purpose models.',
            'created_at' => now(),
        ]);

        Adjudication::create([
            'debate_id'    => $debate->id,
            'total_score'  => 77,
            'matter_score' => 31,
            'manner_score' => 24,
            'method_score' => 22,
            'verdict'      => 'A disciplined round with one weak warrant.',
            'fallacies'    => [[
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

        return $debate->fresh();
    }

    public function test_the_report_states_the_mark_and_the_band_it_fell_in(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->actingAs($user)
            ->get(route('debates.feedback', $debate->id))
            ->assertOk()
            ->getContent();

        // The mark, the denominator and the band. A number without the
        // band it fell in is a number the reader has to interpret alone.
        $this->assertStringContainsString('rpt-mark-value', $html);
        $this->assertStringContainsString('>77<', $html);
        $this->assertStringContainsString('out of 100', $html);

        // The band itself — the word that tells the reader what 77 means.
        // Asserted on the band element itself, so the word "Score" in
        // the "Scorecard" heading cannot satisfy it by accident.
        $this->assertMatchesRegularExpression(
            '/class="rpt-mark-band"[^>]*>\s*Strong\s*</s',
            $html
        );
    }

    public function test_the_report_shows_every_criterion_with_its_band(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->actingAs($user)
            ->get(route('debates.feedback', $debate->id))
            ->assertOk()
            ->getContent();

        // The three criteria, named and scored.
        $this->assertStringContainsString('Matter', $html);
        $this->assertStringContainsString('Manner', $html);
        $this->assertStringContainsString('Method', $html);

        // Each score as a share of its own maximum, so the rows can be
        // compared with each other.
        $this->assertStringContainsString('31<span>/40</span>', $html);
        $this->assertStringContainsString('24<span>/30</span>', $html);
        $this->assertStringContainsString('22<span>/30</span>', $html);
    }

    public function test_the_report_shows_the_judges_reasoning_errors(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->actingAs($user)
            ->get(route('debates.feedback', $debate->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Reasoning errors', $html);
        $this->assertStringContainsString('Unwarranted Assumption', $html);
        $this->assertStringContainsString('The claim was never supported.', $html);
    }

    public function test_the_report_groups_the_findings_by_kind(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->actingAs($user)
            ->get(route('debates.feedback', $debate->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Findings', $html);
        $this->assertStringContainsString('A clear opening framing.', $html);
        $this->assertStringContainsString('The rebuttal never answered the case.', $html);
    }

    public function test_the_report_is_numbered_and_listed_in_the_contents(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->actingAs($user)
            ->get(route('debates.feedback', $debate->id))
            ->assertOk()
            ->getContent();

        // The sections are numbered, and the contents panel lists the
        // same numbers. A reader can point at "section 03" and mean
        // the same thing as the contents panel does.
        foreach (['01', '02', '03'] as $number) {
            $this->assertStringContainsString($number, $html);
        }

        $this->assertStringContainsString('rpt-toc-link', $html);
        $this->assertStringContainsString('aria-label="Report contents"', $html);
    }

    public function test_the_report_does_not_repeat_the_verdict(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->actingAs($user)
            ->get(route('debates.feedback', $debate->id))
            ->assertOk()
            ->getContent();

        // The judge's verdict is the report's headline. It appeared once
        // on the cover and once again in section 01 — the same paragraph
        // twice on one page, in the same words, which reads as a
        // printing error rather than as emphasis.
        $this->assertSame(
            1,
            substr_count($html, 'A disciplined round with one weak warrant.'),
            'The verdict should appear once, not once per section.',
        );
    }

    public function test_the_report_places_the_mark_on_the_grade_ladder(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->actingAs($user)
            ->get(route('debates.feedback', $debate->id))
            ->assertOk()
            ->getContent();

        // The ladder shows the whole scale, so the mark has context.
        $this->assertStringContainsString('rpt-ladder', $html);
        $this->assertStringContainsString('Distinction', $html);
        $this->assertStringContainsString('Below Pass', $html);
    }

    public function test_the_report_shows_the_transcript_for_reference(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->actingAs($user)
            ->get(route('debates.feedback', $debate->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('The record', $html);
        $this->assertStringContainsString(
            'We should regulate the deployment of general-purpose models.',
            $html,
        );
    }

    public function test_the_report_is_not_open_to_another_account(): void
    {
        $owner  = User::factory()->create();
        $debate = $this->makeDebate($owner);

        $this->actingAs(User::factory()->create())
            ->get(route('debates.feedback', $debate->id))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // The brief
    // -------------------------------------------------------------------------

    public function test_the_report_opens_with_the_short_version(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->actingAs($user)
            ->get(route('debates.feedback', $debate->id))
            ->assertOk()
            ->getContent();

        // The brief is the reader's answer before the detail: the mark,
        // what worked, what cost points, and what to do.
        $this->assertStringContainsString('rpt-brief', $html);
        $this->assertStringContainsString('The short version', $html);
        $this->assertStringContainsString('Your mark', $html);
        $this->assertStringContainsString('What worked', $html);
        $this->assertStringContainsString('What cost you', $html);
    }

    public function test_the_brief_comes_before_the_detail_it_summarises(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->actingAs($user)
            ->get(route('debates.feedback', $debate->id))
            ->assertOk()
            ->getContent();

        $brief = strpos($html, 'id="brief"');
        $first = strpos($html, 'id="verdict"');

        $this->assertNotFalse($brief);
        $this->assertNotFalse($first);
        $this->assertLessThan($first, $brief, 'The brief must come before the sections it summarises.');
    }

    public function test_the_report_explains_the_two_scales_it_uses(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->actingAs($user)
            ->get(route('debates.feedback', $debate->id))
            ->assertOk()
            ->getContent();

        // The reader meets an overall band and a criterion band on one
        // page. Without a key, the two read as one vocabulary.
        $this->assertStringContainsString('Two scales', $html);
    }

    public function test_each_criterion_shows_the_scale_it_was_scored_on(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->actingAs($user)
            ->get(route('debates.feedback', $debate->id))
            ->assertOk()
            ->getContent();

        // Every criterion carries its own ladder, with the band the
        // score fell in marked.
        $this->assertStringContainsString('rpt-mini-ladder', $html);
        $this->assertStringContainsString('rpt-mini-band is-current', $html);
    }

    public function test_the_criteria_are_glossed_into_plain_words(): void
    {
        $user   = User::factory()->create();
        $debate = $this->makeDebate($user);

        $html = $this->actingAs($user)
            ->get(route('debates.feedback', $debate->id))
            ->assertOk()
            ->getContent();

        // "Matter", "Manner" and "Method" are the judge's words. The
        // reader needs to be told what they are.
        $this->assertStringContainsString('arguments and evidence', $html);
        $this->assertStringContainsString('your delivery', $html);
        $this->assertStringContainsString('structure and rebuttal', $html);
    }
}
