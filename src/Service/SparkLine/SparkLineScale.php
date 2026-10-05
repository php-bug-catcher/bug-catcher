<?php

declare(strict_types=1);

namespace BugCatcher\Service\SparkLine;

/**
 * What height a value is drawn at, and where the colour ramp runs out.
 *
 * `brendt/php-sparkline` does not clamp: `SparkLineEntry::rebase()` is a plain multiplication, so a
 * value above `maxValue` is given a y coordinate outside the SVG and the browser clips the line
 * flat along the top edge - inside the gradient's last colour. Five errors and five hundred then
 * draw the same picture. Clamping here, before the library ever sees a number, is the only way to
 * keep the line inside the canvas without patching the library.
 *
 * Values are handed over as 0..{@see STEPS} and the renderer sets that as the library's maximum, so
 * `$max` is free to be a count, a millisecond or anything else without the library knowing.
 */
final readonly class SparkLineScale
{
	/** The vertical resolution handed to the library. The SVG is 30px tall, so this is plenty. */
	public const int STEPS = 100;

	/** @param float $max the value at which the ramp reaches its last colour */
	public function __construct(public float $max)
	{
	}

	public function project(float $value): int
	{
		if ($this->max <= 0.0) {
			// A threshold of zero has no "how far up" to answer - everything would be at the top.
			// A flat baseline is the honest shape: coercing $max to 1 would send every non-zero
			// value to the ceiling instead.
			return 0;
		}

		$clamped = min(max($value, 0.0), $this->max);

		return (int)round($clamped / $this->max * self::STEPS);
	}
}
