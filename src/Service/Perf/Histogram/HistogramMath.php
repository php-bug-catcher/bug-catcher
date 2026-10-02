<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Histogram;

use InvalidArgumentException;

/**
 * Histograms add exactly, and this is where they add.
 *
 * Static because it is arithmetic over values, with no state and nothing to configure — unlike
 * {@see PercentileEstimator}, which extractors declare as a collaborator.
 */
final class HistogramMath
{
	/**
	 * @param list<int> $a
	 * @param list<int> $b
	 * @return list<int>
	 */
	public static function add(array $a, array $b): array
	{
		$a = self::normalise($a);
		$b = self::normalise($b);

		foreach ($b as $bin => $count) {
			$a[$bin] += $count;
		}

		return $a;
	}

	/**
	 * @param iterable<list<int>> $histograms
	 * @return list<int>
	 */
	public static function sum(iterable $histograms): array
	{
		$total = HistogramBins::empty();
		foreach ($histograms as $histogram) {
			$total = self::add($total, $histogram);
		}

		return $total;
	}

	/** The number of hits the histogram accounts for. */
	public static function total(array $histogram): int
	{
		return array_sum(self::normalise($histogram));
	}

	/**
	 * Validates a histogram that came off the wire or out of a JSON column — the one place in the
	 * module where a malformed shape is a runtime possibility rather than a programming error.
	 *
	 * @return list<int>
	 */
	public static function normalise(mixed $histogram): array
	{
		if (!is_array($histogram)) {
			throw new InvalidArgumentException(
				sprintf('A histogram is an array of %d bins, got %s.', HistogramBins::COUNT, get_debug_type($histogram)),
			);
		}

		if (count($histogram) !== HistogramBins::COUNT) {
			throw new InvalidArgumentException(
				sprintf('A histogram has %d bins, got %d.', HistogramBins::COUNT, count($histogram)),
			);
		}

		if (array_keys($histogram) !== range(0, HistogramBins::COUNT - 1)) {
			throw new InvalidArgumentException(
				sprintf('A histogram is a list keyed by bins 0 to %d.', HistogramBins::COUNT - 1),
			);
		}

		foreach ($histogram as $bin => $count) {
			if (!is_int($count)) {
				throw new InvalidArgumentException(
					sprintf('A histogram holds integer counts, bin %d is %s.', $bin, get_debug_type($count)),
				);
			}
			if ($count < 0) {
				throw new InvalidArgumentException(sprintf('Bin %d holds a negative count of %d.', $bin, $count));
			}
		}

		return $histogram;
	}
}
