<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * The rubric's own vocabulary.
 *
 * The report shows two scales: the overall band the mark falls in, and
 * the band each criterion falls in. If those two scales share a word,
 * a reader looking at "Strong" in one table and "Strong" in the next
 * has to guess which is which — and the report becomes harder to read
 * exactly where it was supposed to help.
 *
 * These tests hold the two vocabularies apart, and hold the rubric to
 * the shape the report and the judge prompt both rely on.
 */
class RubricTest extends TestCase
{
    /**
     * The overall band and the criterion band must not share a name.
     */
    public function test_the_two_band_vocabularies_do_not_collide(): void
    {
        $grades = array_column(config('debate.rubric.grades', []), 'label');

        foreach (config('debate.rubric.criteria', []) as $key => $criterion) {
            $bands = array_column($criterion['bands'] ?? [], 'label');

            $shared = array_intersect($grades, $bands);

            $this->assertSame(
                [],
                array_values($shared),
                "The overall bands and the {$criterion['label']} bands share a word: "
                . implode(', ', $shared) . '. The report shows both scales, so they must not.',
            );
        }
    }

    /**
     * Every band must carry a name and the sentence that describes it.
     */
    public function test_every_band_carries_a_label_and_a_note(): void
    {
        foreach (config('debate.rubric.grades', []) as $grade) {
            $this->assertNotSame('', $grade['label'] ?? '');
            $this->assertNotSame('', $grade['summary'] ?? '');
        }

        foreach (config('debate.rubric.criteria', []) as $criterion) {
            foreach ($criterion['bands'] ?? [] as $band) {
                $this->assertNotSame('', $band['label'] ?? '');
                $this->assertNotSame('', $band['note'] ?? '');
            }
        }
    }

    /**
     * The bands must be ordered, and must reach down to zero — a scale
     * with a hole at the bottom cannot place a low mark.
     */
    public function test_the_bands_run_from_the_top_down_to_zero(): void
    {
        $grades = array_column(config('debate.rubric.grades', []), 'min');

        // The lowest band must start at zero, or a mark of 0 has no
        // band to fall in.
        $this->assertSame(0, (int) end($grades), 'The lowest band must start at zero.');

        $sorted = $grades;
        rsort($sorted);

        $this->assertSame($sorted, $grades, 'The bands are written from the top down.');
    }

    /**
     * The three criteria must add up to a hundred, or the percentages
     * the report prints are shares of the wrong thing.
     */
    public function test_the_criteria_add_up_to_a_hundred(): void
    {
        $total = 0;

        foreach (config('debate.rubric.criteria', []) as $criterion) {
            $total += (int) ($criterion['weight'] ?? 0);
        }

        $this->assertSame(100, $total);
    }

    /**
     * The criterion bands must be ordered and reach zero, and their top
     * band must be reachable.
     */
    public function test_each_criterion_bands_run_from_the_top_down_to_zero(): void
    {
        foreach (config('debate.rubric.criteria', []) as $criterion) {
            $mins = array_column($criterion['bands'] ?? [], 'min');

            $this->assertNotEmpty($mins, "{$criterion['label']} has no bands.");
            $this->assertSame(0, (int) end($mins), "{$criterion['label']}'s lowest band must start at zero.");

            $sorted = $mins;
            rsort($sorted);

            $this->assertSame($sorted, $mins, "{$criterion['label']}'s bands are written from the top down.");

            // The top band must start below the criterion's maximum,
            // or the band can never be reached.
            $this->assertLessThan(
                (int) $criterion['weight'],
                (int) $mins[0],
                "{$criterion['label']}'s top band is out of reach.",
            );
        }
    }

    /**
     * The band the criterion scores are judged against must be a
     * meaningful spread — a rubric where every score lands in the same
     * band is not a rubric.
     */
    public function test_the_criterion_bands_are_spread_across_the_criterion(): void
    {
        foreach (config('debate.rubric.criteria', []) as $criterion) {
            $mins = array_map('intval', array_column($criterion['bands'] ?? [], 'min'));
            $weight = (int) $criterion['weight'];

            $this->assertGreaterThanOrEqual(
                3,
                count($mins),
                "{$criterion['label']} needs enough bands to say something.",
            );

            // The bands must be spread out, not clustered at one end.
            $spread = $mins[0] - end($mins);

            $this->assertGreaterThan(
                $weight * 0.4,
                $spread,
                "{$criterion['label']}'s bands are clustered and cannot distinguish a good round from a poor one.",
            );
        }
    }
}
