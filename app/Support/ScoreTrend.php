<?php

namespace App\Support;

/**
 * The geometry of a score trend chart.
 *
 * The chart is drawn as an SVG plot with the labels outside it, so this
 * class only has to answer one question: where does each score sit?
 *
 * The coordinate space is a fixed 640 × 200 box. The plot is stretched
 * to whatever width it is given, so every point's horizontal position is
 * its share of the width and its vertical position is its share of the
 * height. A score of 100 sits at the top of the plot and 0 at the
 * bottom, and anything between sits proportionally in between.
 */
class ScoreTrend
{
    public const WIDTH = 640;

    public const HEIGHT = 200;

    /**
     * The margin inside the view box.
     *
     * The first and last points sit on the edges of the plot, and the
     * readings sit above and below their points. Without a margin they
     * would be clipped by the edge of the card — a dot cut in half, or
     * a reading for a perfect score with nowhere to go.
     */
    public const PADDING_X = 12;

    public const PADDING_Y = 10;

    /**
     * The score values the plot is marked at.
     *
     * @var array<int, int>
     */
    private const GRIDLINES = [0, 25, 50, 75, 100];

    /**
     * @param  array<int, array{score: int, label: string}>  $points  Oldest first.
     */
    public function __construct(private readonly array $points)
    {
    }

    public function isEmpty(): bool
    {
        return $this->points === [];
    }

    public function count(): int
    {
        return count($this->points);
    }

    /**
     * The points themselves, oldest first.
     *
     * @return array<int, array{score: int, label: string}>
     */
    public function points(): array
    {
        return $this->points;
    }

    /**
     * The scores on their own.
     *
     * @return array<int, int>
     */
    public function scores(): array
    {
        return array_map(fn (array $point): int => $point['score'], $this->points);
    }

    /**
     * The x coordinate of each point. A single point sits in the middle
     * rather than on the left edge, so it reads as a lone reading rather
     * than as the start of a run.
     *
     * @return array<int, float>
     */
    public function xs(): array
    {
        $count = $this->count();

        if ($count === 0) {
            return [];
        }

        $usable = self::WIDTH - (self::PADDING_X * 2);

        if ($count === 1) {
            return [self::WIDTH / 2];
        }

        return array_map(
            fn (int $index): float => self::PADDING_X + ($index / ($count - 1)) * $usable,
            range(0, $count - 1),
        );
    }

    /**
     * The y coordinate of each point, measured from the top of the plot.
     *
     * @return array<int, float>
     */
    public function ys(): array
    {
        return array_map(
            fn (array $point): float => self::yForScore($point['score']),
            $this->points,
        );
    }

    /**
     * The y coordinate of a score on the same scale as ys().
     */
    public function yFor(int $score): float
    {
        return self::yForScore($score);
    }

    /**
     * The y coordinate of a score, without needing a set of points.
     * The empty chart draws its gridlines from this.
     */
    public static function yForScore(int $score): float
    {
        $clamped = max(0, min(100, (float) $score));

        $usable = self::HEIGHT - (self::PADDING_Y * 2);

        return self::PADDING_Y + (1 - ($clamped / 100)) * $usable;
    }

    /**
     * Where a score sits down the plot, as a percentage of its height.
     *
     * The scale labels beside the plot use this, so a label and the
     * gridline it names cannot drift apart: both are derived from the
     * same number.
     */
    public static function topPercentFor(int $score): float
    {
        return (self::yForScore($score) / self::HEIGHT) * 100;
    }

    /**
     * The line through the points.
     */
    public function linePath(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $xs = $this->xs();
        $ys = $this->ys();

        $path = 'M ' . round($xs[0], 2) . ' ' . round($ys[0], 2);

        foreach ($xs as $index => $x) {
            if ($index === 0) {
                continue;
            }

            $path .= ' L ' . round($x, 2) . ' ' . round($ys[$index], 2);
        }

        return $path;
    }

    /**
     * The same line closed down to the baseline, for the fill beneath it.
     */
    public function areaPath(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $xs = $this->xs();
        $ys = $this->ys();
        $last = $this->count() - 1;
        $baseline = self::yForScore(0);

        return $this->linePath()
            . ' L ' . round($xs[$last], 2) . ' ' . round($baseline, 2)
            . ' L ' . round($xs[0], 2) . ' ' . round($baseline, 2)
            . ' Z';
    }

    /**
     * The gridlines, as score values. Zero is the baseline and 100 the
     * ceiling — the two the reader is most likely to want to check
     * against.
     *
     * @return array<int, int>
     */
    public function gridlines(): array
    {
        return self::GRIDLINES;
    }

    /**
     * The gridlines, without needing a set of points.
     *
     * @return array<int, int>
     */
    public static function gridlineValues(): array
    {
        return self::GRIDLINES;
    }

    /**
     * The date under the left-hand end of the line.
     */
    public function firstLabel(): ?string
    {
        return $this->points[0]['label'] ?? null;
    }

    /**
     * The date under the right-hand end of the line.
     */
    public function lastLabel(): ?string
    {
        return $this->points[$this->count() - 1]['label'] ?? null;
    }

    /**
     * Scores are held between 0 and 100. A reading outside that range
     * would put a point off the top or the bottom of the plot, so it is
     * pulled back to the edge rather than drawn outside the frame.
     */
    private function clamp(int $score): float
    {
        return max(0, min(100, (float) $score));
    }
}
