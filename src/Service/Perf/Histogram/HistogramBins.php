<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Histogram;

use InvalidArgumentException;

/**
 * The fixed logarithmic scale every `duration_histogram` is counted on.
 *
 * A bin is half-open and lower-inclusive — bin 1 is `[1 ms, 2 ms)` — and the last bin holds
 * everything from the final edge upwards, which is why it has no upper edge at all.
 *
 * `EDGES_MS` is duplicated from `BugCatcher\PerfCollector\Histogram\DurationHistogram` in the
 * collector package on purpose: the two packages share no code, and the collector must install
 * into an application that has never heard of this bundle. The duplication is pinned by
 * `HistogramBinsTest`, so the edges cannot drift apart silently — changing them is a migration of
 * stored data, not an edit.
 */
final class HistogramBins
{
	/** @var list<int> */
	public const array EDGES_MS = [1, 2, 5, 10, 25, 50, 100, 250, 500, 1_000, 2_000, 5_000, 10_000, 30_000, 60_000];

	public const int COUNT = 16;

	/** @return list<int> */
	public static function empty(): array
	{
		return array_fill(0, self::COUNT, 0);
	}

	/** Inclusive: a duration equal to this edge belongs to this bin. */
	public static function lowerEdgeMs(int $bin): float
	{
		self::assertBin($bin);

		return $bin === 0 ? 0.0 : (float)self::EDGES_MS[$bin - 1];
	}

	/** Exclusive, and null for the overflow bin, which nothing bounds from above. */
	public static function upperEdgeMs(int $bin): ?float
	{
		self::assertBin($bin);

		return $bin === self::COUNT - 1 ? null : (float)self::EDGES_MS[$bin];
	}

	/**
	 * The bin a duration lands in — the same decision
	 * `DurationHistogram::record()` makes on the collector side.
	 */
	public static function binFor(float $milliseconds): int
	{
		foreach (self::EDGES_MS as $bin => $edge) {
			if ($milliseconds < $edge) {
				return $bin;
			}
		}

		return self::COUNT - 1;
	}

	private static function assertBin(int $bin): void
	{
		if ($bin < 0 || $bin >= self::COUNT) {
			throw new InvalidArgumentException(
				sprintf('Bin %d is outside the histogram scale of %d bins.', $bin, self::COUNT),
			);
		}
	}
}
