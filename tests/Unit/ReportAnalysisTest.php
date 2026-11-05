<?php

namespace Tests\Unit;

use App\Models\Adjudication;
use App\Models\Debate;
use App\Models\Motion;
use App\Models\Persona;
use App\Models\Round;
use App\Models\Turn;
use App\Models\User;
use App\Support\ReportAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The analysis behind the adjudication report.
 *
 * Every number the report shows is derived here, so the arithmetic is
 * the product. A band that lands in the wrong place, a percentage that
 * compares a /30 criterion against a /40 one as though they were the
 * same, or a quote attributed to the speaker who did not say it — each
 * of those would reach the reader as fact.
 */
class ReportAnalysisTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Builds a debate with a transcript and an adjudication.
     *
     * @param  array<string, mixed>  $adjudication
     */
    private function makeDebate(array $adjudication = [], string $language = 'en'): Debate
    {
        $this->seed(\Database\Seeders\PersonaSeeder::class);

        $user = User::factory()->create();

        $motion = Motion::create([
            'text_en'  => 'This House would regulate open-source AI.',
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
            'language'   => $language,
            'status'     => 'adjudicated',
        ]);

        $this->addRound($debate, 'opening', 1, [
            'user' => 'We should regulate the deployment of general-purpose models.',
            'ai'   => 'Regulation slows innovation and chills open research.',
        ]);

        $this->addRound($debate, 'rebuttal', 2, [
            'user' => 'Your innovation argument ignores the externality of unchecked deployment.',
            'ai'   => 'That externality is speculative and poorly evidenced.',
        ]);

        $this->addRound($debate, 'closing', 3, [
            'user' => 'The risks are real and the evidence for them is growing.',
            'ai'   => 'The harms have been exaggerated by certain labs.',
        ]);

        Adjudication::create(array_merge([
            'debate_id'    => $debate->id,
            'total_score'  => 77,
            'matter_score' => 31,
            'manner_score' => 24,
            'method_score' => 22,
            'verdict'      => 'A disciplined round that ran out of time before the Closing.',
            'fallacies'    => [],
            'feedback_bullets' => [],
        ], $adjudication));

        return $debate->fresh();
    }

    /**
     * @param  array<string, string>  $turns
     */
    private function addRound(Debate $debate, string $phase, int $order, array $turns): void
    {
        $round = Round::create([
            'debate_id'  => $debate->id,
            'phase'      => $phase,
            'phase_order'=> $order,
        ]);

        foreach ($turns as $speaker => $transcript) {
            Turn::create([
                'round_id'    => $round->id,
                'speaker'     => $speaker,
                'transcript'  => $transcript,
                'created_at'  => now(),
            ]);
        }
    }

    private function analysis(Debate $debate): ReportAnalysis
    {
        return new ReportAnalysis($debate, $debate->adjudication);
    }

    // -------------------------------------------------------------------------
    // The mark
    // -------------------------------------------------------------------------

    public function test_the_grade_is_the_band_the_total_falls_in(): void
    {
        $debate = $this->makeDebate(['total_score' => 77]);

        $grade = $this->analysis($debate)->grade();

        // 77 clears the 70 floor of 'Strong' but not the 85 floor of
        // 'Distinction'.
        $this->assertSame(77, $grade['total']);
        $this->assertSame('Strong', $grade['label']);
        $this->assertSame('Distinction', $grade['next_label']);
        $this->assertSame(8, $grade['next_gap']);
    }

    public function test_the_top_band_has_no_band_above_it(): void
    {
        $debate = $this->makeDebate(['total_score' => 90]);

        $grade = $this->analysis($debate)->grade();

        $this->assertSame('Distinction', $grade['label']);
        $this->assertNull($grade['next_label']);
        $this->assertNull($grade['next_gap']);
    }

    public function test_the_ladder_places_the_mark_on_exactly_one_band(): void
    {
        $debate = $this->makeDebate(['total_score' => 77]);

        $ladder = $this->analysis($debate)->ladder();

        $current = array_filter($ladder, fn (array $band) => $band['current']);

        $this->assertCount(1, $current, 'The mark must sit on exactly one band.');

        // The band below the mark was passed; the band above was not.
        $passed = array_filter($ladder, fn (array $band) => $band['passed']);
        $ahead  = array_filter($ladder, fn (array $band) => $band['ahead']);

        $this->assertNotEmpty($passed);
        $this->assertNotEmpty($ahead);
    }

    public function test_the_ladder_reads_from_the_top_band_down(): void
    {
        $debate = $this->makeDebate();

        $ladder = $this->analysis($debate)->ladder();

        $labels = array_column($ladder, 'label');

        $this->assertSame('Distinction', $labels[0]);
        $this->assertSame('Below Pass', end($labels));
    }

    // -------------------------------------------------------------------------
    // The scorecard
    // -------------------------------------------------------------------------

    public function test_a_criterion_is_a_percentage_of_its_own_maximum(): void
    {
        $debate = $this->makeDebate([
            'matter_score' => 31, // 40
            'manner_score' => 24, // 30
            'method_score' => 22, // 30
        ]);

        $criteria = collect($this->analysis($debate)->criteria())->keyBy('key');

        // 31 of 40 is 78%; 24 of 30 is 80%. The raw scores would put
        // Matter ahead of Manner, but the two criteria have different
        // maximums and cannot be compared on their raw numbers.
        $this->assertSame(78, $criteria['matter']['percent']);
        $this->assertSame(80, $criteria['manner']['percent']);
        $this->assertSame(73, $criteria['method']['percent']);
    }

    public function test_a_criterion_carries_the_band_it_fell_in(): void
    {
        $debate = $this->makeDebate(['matter_score' => 31]);

        $matter = collect($this->analysis($debate)->criteria())->firstWhere('key', 'matter');

        // 31 of 40 is in the "Solid" band of the criterion ladder.
        $this->assertSame('Solid', $matter['band']);
        $this->assertNotSame('', $matter['band_note']);
    }

    // -------------------------------------------------------------------------
    // The readout
    // -------------------------------------------------------------------------

    public function test_the_readout_names_the_strongest_and_weakest_criterion(): void
    {
        $debate = $this->makeDebate([
            'matter_score' => 31, // 78%
            'manner_score' => 24, // 80%
            'method_score' => 22, // 73%
        ]);

        $readout = $this->analysis($debate)->readout();

        $this->assertTrue($readout['available']);
        $this->assertSame('Manner', $readout['strongest']['label']);
        $this->assertSame('Method', $readout['weakest']['label']);
    }

    public function test_the_readout_says_how_many_marks_went_unclaimed(): void
    {
        $debate = $this->makeDebate([
            'matter_score' => 31, // 9 left
            'manner_score' => 24, // 6 left
            'method_score' => 22, // 8 left
        ]);

        $prose = implode(' ', $this->analysis($debate)->readout()['sentences']);

        // 9 + 6 + 8 of 100.
        $this->assertStringContainsString('23 of the 100 marks', $prose);
    }

    public function test_the_readout_says_how_far_the_weakest_criterion_fell_short(): void
    {
        $debate = $this->makeDebate(['method_score' => 22]);

        $prose = implode(' ', $this->analysis($debate)->readout()['sentences']);

        // 22 is four points short of the 26 that "Excellent" starts at.
        $this->assertStringContainsString('4 points short of Excellent', $prose);
    }

    // -------------------------------------------------------------------------
    // The criterion ladder
    // -------------------------------------------------------------------------

    public function test_each_criterion_carries_its_own_scale_with_the_band_marked(): void
    {
        $debate = $this->makeDebate(['matter_score' => 31]);

        $matter = collect($this->analysis($debate)->criteria())->firstWhere('key', 'matter');

        $current = array_filter($matter['ladder'], fn (array $band) => $band['current']);

        $this->assertCount(1, $current, 'Exactly one band is the band the score fell in.');
        $this->assertSame('Solid', $current[array_key_first($current)]['label']);

        // Every band states the points it starts at, so the reader can
        // see the line they fell short of rather than trust a word.
        foreach ($matter['ladder'] as $band) {
            $this->assertArrayHasKey('min', $band);
            $this->assertNotSame('', $band['label']);
        }
    }

    public function test_a_criterion_says_how_far_it_fell_short_of_the_next_band(): void
    {
        $debate = $this->makeDebate(['method_score' => 22]);

        $method = collect($this->analysis($debate)->criteria())->firstWhere('key', 'method');

        $this->assertNotNull($method['next_band']);
        $this->assertSame('Excellent', $method['next_band']['label']);
        $this->assertSame(4, $method['next_band']['gap']);
    }

    public function test_the_top_criterion_band_has_nothing_above_it(): void
    {
        $debate = $this->makeDebate(['matter_score' => 40]);

        $matter = collect($this->analysis($debate)->criteria())->firstWhere('key', 'matter');

        $this->assertNull($matter['next_band']);
    }

    // -------------------------------------------------------------------------
    // The brief
    // -------------------------------------------------------------------------

    public function test_the_brief_says_the_mark_what_worked_and_what_cost_points(): void
    {
        $debate = $this->makeDebate([
            'matter_score' => 31,
            'manner_score' => 24,
            'method_score' => 22,
            'feedback_bullets' => ['[ISSUE] In the Opening, the mechanism was never stated.'],
        ]);

        $brief = $this->analysis($debate)->brief();

        $this->assertTrue($brief['available']);

        $labels = array_column($brief['lines'], 'label');

        $this->assertContains('Your mark', $labels);
        $this->assertContains('What worked', $labels);
        $this->assertContains('What cost you', $labels);
        $this->assertContains('Do this next', $labels);

        $prose = implode(' ', array_column($brief['lines'], 'text'));

        $this->assertStringContainsString('77 out of 100', $prose);
        $this->assertStringContainsString('Strong', $prose);
    }

    public function test_the_brief_says_the_criteria_in_plain_words(): void
    {
        $debate = $this->makeDebate([
            'manner_score' => 24,
            'method_score' => 22,
        ]);

        $prose = implode(' ', array_column($this->analysis($debate)->brief()['lines'], 'text'));

        // "Manner" and "Method" are the judge's words; the brief speaks
        // the reader's.
        $this->assertStringContainsString('delivery', $prose);
        $this->assertStringContainsString('structure and rebuttal', $prose);
    }

    public function test_the_brief_explains_the_two_scales_the_report_uses(): void
    {
        $debate = $this->makeDebate();

        $key = $this->analysis($debate)->brief()['key'];

        // The reader meets an overall band and a criterion band and
        // nothing else tells them how the two relate.
        $this->assertStringContainsString('Two scales', $key);
        $this->assertStringContainsString('Strong', $key);
    }

    public function test_a_finding_keeps_its_full_stop_when_a_quote_is_lifted_out(): void
    {
        $debate = $this->makeDebate([
            'feedback_bullets' => [
                '[STRENGTH] Your "duty of care" framing was your most consistent through-line.',
            ],
        ]);

        $item = $this->analysis($debate)->findings()[0]['items'][0];

        // Lifting the quote out of the middle of the sentence takes the
        // closing full stop with it. A finding that runs off the end of
        // its own sentence reads as a truncation.
        $this->assertStringEndsWith('.', $item['text']);
    }

    public function test_a_finding_without_a_quote_is_left_exactly_as_the_judge_wrote_it(): void
    {
        $debate = $this->makeDebate([
            'feedback_bullets' => [
                '[ISSUE] Enforcement mechanism was never addressed.',
            ],
        ]);

        $item = $this->analysis($debate)->findings()[0]['items'][0];

        $this->assertSame('Enforcement mechanism was never addressed.', $item['text']);
    }

    // -------------------------------------------------------------------------
    // What the report is allowed to claim
    // -------------------------------------------------------------------------

    public function test_a_short_phase_is_not_reported_as_a_missing_speech(): void
    {
        $debate = $this->makeDebate();

        $rebuttal = collect($this->analysis($debate)->phases())->firstWhere('key', 'rebuttal');

        // The rebuttal was answered — it was short. Saying it was
        // "unanswered" or "not landed" would be a claim the number
        // does not support.
        $this->assertStringNotContainsString('unanswered', $rebuttal['note']['text']);
        $this->assertStringNotContainsString('never', $rebuttal['note']['text']);
        $this->assertStringNotContainsString('without a closing argument', $rebuttal['note']['text']);
    }

    public function test_the_report_attributes_its_actionable_line_to_the_judge(): void
    {
        $debate = $this->makeDebate([
            'feedback_bullets' => [
                '[ISSUE] In the Opening, the mechanism was never stated.',
            ],
        ]);

        $prose = implode(' ', array_column($this->analysis($debate)->brief()['lines'], 'text'));

        // The report does not claim to have worked out which flaw cost
        // the round the most. It reports the one the judge flagged,
        // and says so.
        $this->assertStringContainsString('Start with the Opening', $prose);
        $this->assertStringNotContainsString('most urgent', $prose);
    }

    public function test_the_readout_attributes_the_flagged_issue_to_the_judge(): void
    {
        $debate = $this->makeDebate([
            'feedback_bullets' => [
                '[ISSUE] In the Opening, the mechanism was never stated.',
            ],
        ]);

        $prose = implode(' ', $this->analysis($debate)->readout()['sentences']);

        $this->assertStringContainsString('the judge flagged', $prose);
        $this->assertStringNotContainsString('most urgent change', $prose);
    }

    public function test_the_ladder_states_the_gap_to_the_band_above_in_points(): void
    {
        $debate = $this->makeDebate(['total_score' => 77]);

        $ahead = collect($this->analysis($debate)->ladder())->firstWhere('ahead', true);

        // The Distinction band starts at 85; 77 is eight short.
        $this->assertSame(8, $ahead['gap']);
    }

    // -------------------------------------------------------------------------
    // The findings
    // -------------------------------------------------------------------------

    public function test_findings_are_grouped_by_kind_in_a_fixed_order(): void
    {
        $debate = $this->makeDebate([
            'feedback_bullets' => [
                '[STRENGTH] The Opening framed the motion cleanly.',
                '[ISSUE] The Closing never answered the case.',
                '[TIP] Cite a named study in the Rebuttal.',
                '[DRILL] Practise a 30-second mechanism before the next round.',
            ],
        ]);

        $kinds = array_column($this->analysis($debate)->findings(), 'kind');

        // The order is the rubric's, not the order the judge happened
        // to write them in: what went well, what did not, what to do
        // about it, what to practise.
        $this->assertSame(['strength', 'issue', 'tip', 'drill'], $kinds);
    }

    public function test_an_untagged_finding_is_still_reported(): void
    {
        $debate = $this->makeDebate([
            'feedback_bullets' => ['Something worth reading without a tag.'],
        ]);

        $groups = $this->analysis($debate)->findings();

        $this->assertCount(1, $groups);
        $this->assertSame('note', $groups[0]['kind']);
    }

    public function test_a_finding_anchored_to_a_named_phase_is_placed(): void
    {
        $debate = $this->makeDebate([
            'feedback_bullets' => ['[ISSUE] In the Opening, the mechanism was never stated.'],
        ]);

        $item = $this->analysis($debate)->findings()[0]['items'][0];

        $this->assertSame('opening', $item['phase']);
    }

    public function test_a_lowercase_phase_mention_is_not_treated_as_the_phase(): void
    {
        $debate = $this->makeDebate([
            'feedback_bullets' => ['[ISSUE] Your rebuttal was slower than your opening.'],
        ]);

        $item = $this->analysis($debate)->findings()[0]['items'][0];

        // "your rebuttal" is a reference to the speech, not a claim
        // that the finding is about the Rebuttal phase. The capitalised
        // name is what marks the phase.
        $this->assertNull($item['phase']);
    }

    public function test_a_quote_is_only_reported_when_the_speaker_said_it(): void
    {
        $debate = $this->makeDebate([
            'feedback_bullets' => [
                '[STRENGTH] Your "externality of unchecked deployment" was the round\'s best line.',
            ],
        ]);

        $item = $this->analysis($debate)->findings()[0]['items'][0];

        // The speaker said "unchecked deployment" in the Rebuttal, so
        // the quote is real and the finding is anchored to that phase.
        $this->assertSame('externality of unchecked deployment', $item['quote']);
        $this->assertSame('rebuttal', $item['phase']);
    }

    public function test_a_quote_the_speaker_never_said_is_not_lifted_into_a_quote(): void
    {
        $debate = $this->makeDebate([
            'feedback_bullets' => [
                '[STRENGTH] Your "brilliant framing of the entire economy" was excellent.',
            ],
        ]);

        $item = $this->analysis($debate)->findings()[0]['items'][0];

        // The phrase is not in the transcript, so it is not lifted out
        // and set beside the speech as something the speaker said. A
        // report must not put words in the speaker's mouth.
        //
        // The judge's own wording is left as they wrote it — the report
        // quotes the judge faithfully, and only declines to vouch for
        // the transcript itself.
        $this->assertNull($item['quote']);
        $this->assertStringContainsString('was excellent', $item['text']);
    }

    public function test_a_lifted_quote_leaves_no_stray_quotation_marks_behind(): void
    {
        $debate = $this->makeDebate([
            'feedback_bullets' => [
                '[STRENGTH] The "externality of unchecked deployment" was your best framing.',
            ],
        ]);

        $item = $this->analysis($debate)->findings()[0]['items'][0];

        $this->assertStringNotContainsString('""', $item['text']);
    }

    public function test_the_closing_recommendation_becomes_the_drill_when_it_is_not_tagged(): void
    {
        $debate = $this->makeDebate([
            'feedback_bullets' => [
                '[STRENGTH] A clear opening framing.',
                '[TIP] Cite a named study.',
                '[TIP] Practise a 30-second mechanism before the next round.',
            ],
        ]);

        $groups = $this->analysis($debate)->findings();
        $kinds  = array_column($groups, 'kind');

        // The prompt's contract puts the practice plan last, so the last
        // recommendation is promoted to the drill.
        $this->assertContains('drill', $kinds);
        $this->assertSame('tip', $kinds[count($kinds) - 2]);

        $drill = collect($groups)->firstWhere('kind', 'drill');

        $this->assertStringContainsString('Practise a 30-second mechanism', $drill['items'][0]['text']);
    }

    public function test_a_tagged_drill_is_left_alone(): void
    {
        $debate = $this->makeDebate([
            'feedback_bullets' => [
                '[STRENGTH] A clear opening framing.',
                '[TIP] Cite a named study.',
                '[DRILL] Practise a 30-second mechanism before the next round.',
            ],
        ]);

        $groups = $this->analysis($debate)->findings();

        $this->assertSame(['strength', 'tip', 'drill'], array_column($groups, 'kind'));
    }

    // -------------------------------------------------------------------------
    // The round
    // -------------------------------------------------------------------------

    public function test_each_phase_carries_its_transcript_and_its_budget(): void
    {
        $debate = $this->makeDebate();

        $phases = $this->analysis($debate)->phases();

        $this->assertCount(3, $phases);
        $this->assertSame('Opening', $phases[0]['label']);
        $this->assertSame(180, $phases[0]['budget']);
        $this->assertSame(120, $phases[1]['budget']);
    }

    public function test_the_speaking_time_is_estimated_from_the_word_count(): void
    {
        $debate = $this->makeDebate();

        $opening = $this->analysis($debate)->phases()[0];

        // Eight words at 140 words a minute is about three seconds.
        $this->assertSame(8, $opening['words']);
        $this->assertEqualsWithDelta(3, $opening['seconds'], 1);
    }

    public function test_the_delivery_reports_how_much_of_the_time_was_used(): void
    {
        $debate = $this->makeDebate();

        $delivery = $this->analysis($debate)->delivery();

        $this->assertTrue($delivery['available']);
        $this->assertCount(3, $delivery['phases']);

        // A handful of words against seven minutes of budget.
        $this->assertLessThan(10, $delivery['fill']);
    }

    public function test_a_round_with_no_time_budget_reports_no_delivery(): void
    {
        config(['debate.phase_duration' => []]);

        $debate = $this->makeDebate();

        $this->assertFalse($this->analysis($debate)->delivery()['available']);
    }

    public function test_a_phase_that_was_answered_shortly_says_what_it_cost(): void
    {
        $debate = $this->makeDebate();

        $opening = $this->analysis($debate)->phases()[0];

        // Eight words against a three-minute budget. The note says what
        // that means for the opening, rather than leaving the reader
        // to work it out from the percentage.
        $this->assertSame('short', $opening['note']['tone']);
        $this->assertStringContainsString('undeveloped', $opening['note']['text']);
    }

    public function test_a_phase_run_within_its_time_says_so(): void
    {
        $debate = $this->makeDebate();

        $opening = $this->analysis($debate)->phases()[0];

        // The budget is set to the length of the speech, so the phase
        // is neither short nor over.
        config(['debate.phase_duration' => ['opening' => 4, 'rebuttal' => 4, 'closing' => 4]]);

        $opening = $this->analysis($debate)->phases()[0];

        $this->assertSame('ok', $opening['note']['tone']);
    }

    public function test_a_phase_that_ran_over_says_so(): void
    {
        config(['debate.phase_duration' => ['opening' => 1, 'rebuttal' => 1, 'closing' => 1]]);

        $debate = $this->makeDebate();

        $opening = $this->analysis($debate)->phases()[0];

        $this->assertSame('long', $opening['note']['tone']);
    }

    public function test_the_excerpt_stops_at_the_end_of_a_sentence(): void
    {
        $debate = $this->makeDebate();

        $turn = $this->analysis($debate)->phases()[0]['turns'][0];

        // The excerpt is the opening of the speech, not the whole of
        // it — the full text is in the record.
        $this->assertStringEndsWith('.', $turn['excerpt']);
        $this->assertStringNotContainsString('unchecked deployment', $turn['excerpt']);
    }

    public function test_bangla_is_counted_by_length_rather_than_by_spaces(): void
    {
        $debate = $this->makeDebate(language: 'bn');

        // The transcript is English text, but the debate is in Bangla,
        // so the count falls back to characters. Either way it must be
        // a number, not a crash, and not a count of whitespace.
        $words = $this->analysis($debate)->phases()[0]['words'];

        $this->assertIsInt($words);
        $this->assertGreaterThan(0, $words);
    }

    // -------------------------------------------------------------------------
    // The record
    // -------------------------------------------------------------------------

    public function test_the_record_holds_every_turn_in_order(): void
    {
        $debate = $this->makeDebate();

        $record = $this->analysis($debate)->transcript();

        $this->assertCount(6, $record);
        $this->assertSame('Opening', $record[0]['phase']);
        $this->assertSame('user', $record[0]['speaker']);
        $this->assertSame('Government', $record[0]['side']);
        $this->assertSame('ai', $record[1]['speaker']);
    }

    public function test_the_analysis_returns_every_section_the_report_needs(): void
    {
        $debate = $this->makeDebate();

        $analysis = $this->analysis($debate)->toArray();

        foreach (['grade', 'criteria', 'readout', 'findings', 'phases', 'delivery', 'transcript', 'ladder'] as $key) {
            $this->assertArrayHasKey($key, $analysis, "The report needs \"{$key}\".");
        }
    }
}
