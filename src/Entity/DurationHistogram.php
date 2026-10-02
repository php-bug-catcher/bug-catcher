<?php

declare(strict_types=1);

namespace BugCatcher\Entity;

use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Histogram\HistogramMath;

/**
 * The sixteen histogram bins of a {@see PerfBucket}, as sixteen columns.
 *
 * A Doctrine embeddable rather than one JSON column, for three reasons. MySQL before 5.7 has no
 * JSON type at all, and the bundle supports those servers. Adding bins in an upsert is then plain
 * integer arithmetic (`bin_7 = bin_7 + ?`) instead of JSON extraction that MySQL would do in
 * DOUBLE. And a window's percentile becomes `SUM(bin_0), SUM(bin_1), ...` - one aggregate query
 * rather than sixteen numbers decoded per row in PHP.
 *
 * The bin count is a wire format shared with the collector, so the sixteen properties are written
 * out rather than generated: they are the same fixed scale as {@see HistogramBins::EDGES_MS}, and
 * a seventeenth bin is a migration, not an edit.
 */
final class DurationHistogram
{
	private int $bin0 = 0;
	private int $bin1 = 0;
	private int $bin2 = 0;
	private int $bin3 = 0;
	private int $bin4 = 0;
	private int $bin5 = 0;
	private int $bin6 = 0;
	private int $bin7 = 0;
	private int $bin8 = 0;
	private int $bin9 = 0;
	private int $bin10 = 0;
	private int $bin11 = 0;
	private int $bin12 = 0;
	private int $bin13 = 0;
	private int $bin14 = 0;
	private int $bin15 = 0;

	/** @param list<int> $bins */
	public static function fromArray(array $bins): self
	{
		$bins      = HistogramMath::normalise($bins);
		$histogram = new self();

		foreach ($bins as $bin => $count) {
			$histogram->{'bin' . $bin} = $count;
		}

		return $histogram;
	}

	/** @return list<int> counts per bin of {@see HistogramBins} */
	public function toArray(): array
	{
		$bins = [];
		for ($bin = 0; $bin < HistogramBins::COUNT; $bin++) {
			$bins[] = $this->{'bin' . $bin};
		}

		return $bins;
	}

	/** The property each bin is stored in - what the upserter asks the mapping about. */
	public static function fieldFor(int $bin): string
	{
		if ($bin < 0 || $bin >= HistogramBins::COUNT) {
			throw new \InvalidArgumentException(
				sprintf('Bin %d is outside the histogram scale of %d bins.', $bin, HistogramBins::COUNT),
			);
		}

		return 'bin' . $bin;
	}
}
