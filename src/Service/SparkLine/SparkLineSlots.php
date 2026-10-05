<?php

declare(strict_types=1);

namespace BugCatcher\Service\SparkLine;

use InvalidArgumentException;

/**
 * A sparse set of buckets, turned into the dense row of values a sparkline is drawn from.
 *
 * `brendt/php-sparkline` 2.x takes values and nothing else: one entry is one x position, and the
 * gaps are the caller's problem. That is the feature the v1 fork of this library existed for, and
 * the one thing that has to be right, because a chart that silently drops an empty bucket draws
 * a line straight across an outage and shifts every point after it.
 *
 * A slot is an integer index, 0 oldest, and the caller decides what one slot spans. That is
 * deliberate: the bug this replaces came from two code paths agreeing on a bucket *timestamp*,
 * one rounding and one flooring, and disagreeing about the newest bucket roughly half the time.
 */
final readonly class SparkLineSlots
{
	/**
	 * @param array<int, int|float> $bySlot value per slot index, holes allowed
	 * @param int                   $slots  how many positions the line has
	 * @return list<float> exactly $slots values, oldest first
	 */
	public function fill(array $bySlot, int $slots, SparkLineGapMode $gap): array
	{
		if ($slots < 1) {
			throw new InvalidArgumentException(sprintf('A sparkline needs at least one slot, %d asked for.', $slots));
		}

		$values = [];
		$last   = 0.0;

		for ($slot = 0; $slot < $slots; $slot++) {
			if (isset($bySlot[$slot])) {
				$last = (float)$bySlot[$slot];
				$values[] = $last;

				continue;
			}

			$values[] = $gap === SparkLineGapMode::Hold ? $last : 0.0;
		}

		return $values;
	}
}
