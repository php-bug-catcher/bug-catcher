<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Histogram;

use InvalidArgumentException;

/**
 * Percentiles out of a histogram, by linear interpolation inside the bin the rank falls in.
 *
 * The error is bounded by the width of that bin and never by how often the data was rolled up —
 * that is the whole reason a histogram is shipped instead of a mean and a maximum. The estimate is
 * monotone in the quantile, which matters because the dashboard draws p50 and p95 on one axis.
 */
final class PercentileEstimator
{
	public function p50(array $histogram): ?float
	{
		return $this->estimateMs($histogram, 0.5);
	}

	public function p95(array $histogram): ?float
	{
		return $this->estimateMs($histogram, 0.95);
	}

	public function p99(array $histogram): ?float
	{
		return $this->estimateMs($histogram, 0.99);
	}

	/**
	 * @param list<int> $histogram
	 * @param float $quantile 0.0 to 1.0
	 * @return float|null milliseconds, null when the histogram holds no hits
	 */
	public function estimateMs(array $histogram, float $quantile): ?float
	{
		if ($quantile < 0.0 || $quantile > 1.0) {
			throw new InvalidArgumentException(
				sprintf('A quantile is between 0.0 and 1.0, got %s.', var_export($quantile, true)),
			);
		}

		$histogram = HistogramMath::normalise($histogram);
		$total     = array_sum($histogram);

		if ($total === 0) {
			return null;
		}

		$rank    = $quantile * $total;
		$counted = 0;

		foreach ($histogram as $bin => $count) {
			if ($count === 0) {
				continue;
			}

			if ($counted + $count < $rank) {
				$counted += $count;

				continue;
			}

			$lower = HistogramBins::lowerEdgeMs($bin);
			$upper = HistogramBins::upperEdgeMs($bin);

			if ($upper === null) {
				// Nothing bounds the overflow bin from above, so its lower edge is the only
				// honest answer: "at least a minute".
				return $lower;
			}

			return $lower + ($upper - $lower) * (($rank - $counted) / $count);
		}

		// Unreachable: the loop above cannot pass a rank of at most $total.
		return null;
	}
}
