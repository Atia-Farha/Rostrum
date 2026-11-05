<?php

namespace Tests\Unit;

use App\Support\ScoreTrend;
use PHPUnit\Framework\TestCase;

/**
 * The geometry behind the score trend chart.
 *
 * The chart is drawn from these numbers, so the arithmetic matters: a
 * point in the wrong place draws a trend that never happened.
 */
class ScoreTrendTest extends TestCase
{
    public function test_an_empty_chart_has_no_points_and_no_path(): void
    {
        $plot = new ScoreTrend([]);

        $this->assertTrue($plot->isEmpty());
        $this->assertSame(0, $plot->count());
        $this->assertSame([], $plot->xs());
        $this->assertSame('', $plot->linePath());
        $this->assertSame('', $plot->areaPath());
    }

    public function test_a_single_point_sits_in_the_middle_of_the_plot(): void
    {
        $plot = new ScoreTrend([['score' => 70, 'label' => '01 Nov']]);

        // A lone reading on the left edge would read as the start of a
        // run rather than as one reading on its own.
        $this->assertEqualsWithDelta([320.0], $plot->xs(), 0.01);
    }

    public function test_the_first_and_last_points_sit_inside_the_plot(): void
    {
        $plot = new ScoreTrend([
            ['score' => 40, 'label' => 'a'],
            ['score' => 60, 'label' => 'b'],
            ['score' => 80, 'label' => 'c'],
        ]);

        $xs = $plot->xs();

        $this->assertGreaterThan(0, $xs[0], 'The first point must not touch the left edge.');
        $this->assertLessThan(ScoreTrend::WIDTH, $xs[2], 'The last point must not touch the right edge.');
        $this->assertGreaterThan($xs[0], $xs[1]);
        $this->assertGreaterThan($xs[1], $xs[2]);
    }

    public function test_a_higher_score_sits_higher_on_the_plot(): void
    {
        $plot = new ScoreTrend([
            ['score' => 30, 'label' => 'a'],
            ['score' => 90, 'label' => 'b'],
        ]);

        $ys = $plot->ys();

        $this->assertLessThan($ys[0], $ys[1], 'A higher score must have a smaller y.');
    }

    public function test_a_perfect_score_sits_at_the_top_and_a_zero_at_the_bottom(): void
    {
        $plot = new ScoreTrend([
            ['score' => 0, 'label' => 'a'],
            ['score' => 100, 'label' => 'b'],
        ]);

        $ys = $plot->ys();

        $this->assertEqualsWithDelta(ScoreTrend::HEIGHT - ScoreTrend::PADDING_Y, $ys[0], 0.01);
        $this->assertEqualsWithDelta(ScoreTrend::PADDING_Y, $ys[1], 0.01);
    }

    public function test_a_score_outside_the_scale_is_pulled_back_to_the_edge(): void
    {
        $plot = new ScoreTrend([
            ['score' => -10, 'label' => 'a'],
            ['score' => 140, 'label' => 'b'],
        ]);

        $ys = $plot->ys();

        // A reading outside 0-100 would otherwise be drawn outside the
        // frame and clipped.
        $this->assertEqualsWithDelta(ScoreTrend::HEIGHT - ScoreTrend::PADDING_Y, $ys[0], 0.01);
        $this->assertEqualsWithDelta(ScoreTrend::PADDING_Y, $ys[1], 0.01);
    }

    public function test_the_line_path_starts_at_the_first_point(): void
    {
        $plot = new ScoreTrend([
            ['score' => 50, 'label' => 'a'],
            ['score' => 60, 'label' => 'b'],
        ]);

        $path = $plot->linePath();

        $this->assertStringStartsWith('M ', $path);
        $this->assertSame(1, substr_count($path, 'M '), 'The path should have one move.');
        $this->assertSame(1, substr_count($path, 'L '), 'The path should have one line between two points.');
    }

    public function test_the_area_path_closes_down_to_the_baseline(): void
    {
        $plot = new ScoreTrend([
            ['score' => 50, 'label' => 'a'],
            ['score' => 60, 'label' => 'b'],
        ]);

        $area = $plot->areaPath();

        $this->assertStringEndsWith('Z', $area);
        // The closing points sit on the baseline, so the fill under the
        // line reaches the bottom of the plot.
        $baseline = ScoreTrend::yForScore(0);
        $this->assertStringContainsString((string) round($baseline, 2), $area);
    }

    public function test_the_scale_labels_and_the_gridlines_agree(): void
    {
        // The scale column beside the plot is positioned in CSS
        // percentages and the gridlines inside it are drawn in SVG
        // coordinates. If the two drift, a label sits on the wrong
        // line — so the percentage must resolve to the same place.
        foreach (ScoreTrend::gridlineValues() as $score) {
            $fromPercent = (ScoreTrend::topPercentFor($score) / 100) * ScoreTrend::HEIGHT;

            $this->assertEqualsWithDelta(
                ScoreTrend::yForScore($score),
                $fromPercent,
                0.01,
                "The label for {$score} must sit on its gridline.",
            );
        }
    }

    public function test_the_first_and_last_labels_come_from_the_ends_of_the_run(): void
    {
        $plot = new ScoreTrend([
            ['score' => 50, 'label' => '01 Nov'],
            ['score' => 60, 'label' => '08 Nov'],
            ['score' => 70, 'label' => '15 Nov'],
        ]);

        $this->assertSame('01 Nov', $plot->firstLabel());
        $this->assertSame('15 Nov', $plot->lastLabel());
    }
}
