<?php

declare(strict_types=1);

namespace BugCatcher\Service\SparkLine;

use Brendt\SparkLine\SparkLine;

/**
 * The one place in the bundle that talks to `brendt/php-sparkline`.
 *
 * Both status-list sparklines - a day of errors and a day of p95 - come through here, so the three
 * things the library gets wrong for this dashboard are worked around once:
 *
 * - **It does not clamp.** Handled before we get here, by {@see SparkLineScale}: values arrive
 *   as 0..{@see SparkLineScale::STEPS} and that is what the library is told its maximum is.
 * - **Its x step is an integer.** `floor($width / $slots)` left the line short of the right edge -
 *   at 250px over 96 slots, the last point landed at x=192 and the right quarter was blank. Sizing
 *   the canvas as a whole number of slots instead makes the step exact, so the first point is at
 *   x=0 and the last at x=$width.
 * - **It writes a fixed width onto the `<svg>`.** Traded for a viewBox, so CSS can size the chart
 *   to whatever column it lands in - see `.sparkline svg` in app.css.
 *
 * The colours are `var(--bc-spark-*)` and not hex because the SVG is inlined into the page: the
 * gradient stops resolve against the active theme in the browser, which the server cannot know.
 * Their positions are the library's own `floor(100 / count($colors))`, so with three colours the
 * stops sit at 0/33/66% and the last colour is reached around 71% of the scale. Moving that would
 * mean repeating a colour to shift the steps, which is not worth the indirection.
 */
final readonly class SparkLineRenderer
{
	/** Width of one slot on the drawing surface. The rendered width is CSS's - see above. */
	private const int SLOT_PX = 3;

	private const int HEIGHT = 30;

	/** @param list<float> $values one per slot, oldest first, in $scale's own unit */
	public function render(array $values, SparkLineScale $scale): string
	{
		$points = array_map(static fn(float $value): int => $scale->project($value), $values);

		// A polyline needs two points, and a window can legitimately hold one bucket or none.
		// Repeating the single value keeps it at its own height rather than flattening it to zero.
		$points = match (count($points)) {
			0       => [0, 0],
			1       => [$points[0], $points[0]],
			default => $points,
		};

		$width = (count($points) - 1) * self::SLOT_PX;

		$svg = (new SparkLine(...$points))
			->withDimensions($width, self::HEIGHT)
			->withMaxValue(SparkLineScale::STEPS)
			->withMaxItemAmount(count($points) - 1)
			->withColors('var(--bc-spark-low)', 'var(--bc-spark-mid)', 'var(--bc-spark-high)')
			->make();

		return $this->stretchToContainer($svg);
	}

	/**
	 * `preserveAspectRatio="none"` is what lets a sparkline stretch to fill rather than letterbox,
	 * which is the right trade here because the shape carries the trend, not a scale.
	 *
	 * The library's template opens with a blank line before the `<svg>`, hence the trim: an
	 * anchored pattern matches nothing and would leave the fixed width in place without failing.
	 */
	private function stretchToContainer(string $svg): string
	{
		return preg_replace(
			'/^<svg width="(\d+)" height="(\d+)"/',
			'<svg viewBox="0 0 $1 $2" preserveAspectRatio="none" height="$2"',
			trim($svg),
			1,
		);
	}
}
