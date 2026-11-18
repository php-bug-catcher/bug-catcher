<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Report\Dto;

use BugCatcher\Service\Perf\PerfWindow;

/**
 * A slice of a window - one route, one machine, a whole project - as a single row with every
 * number the page has about it.
 *
 * {@see PerfHealth} is the same read cut down to what a dashboard cell prints; this is the rest of
 * it, for a reader who asks about one route and wants the whole answer. `total` is a
 * {@see PerfTimePoint} because that already is "a bucket's worth, per request": the window is read
 * as one bucket, so its p95 comes off the summed bins and is a percentile, not an average of them.
 */
final readonly class PerfSummary
{
	/**
	 * @param PerfTimePoint $total the whole window, stamped with its start
	 * @param float $maxMs the slowest single request - see {@see TopPathRow::$maxMs} for why p95
	 *     alone cannot describe a run past the top bin
	 * @param float|null $apdex at T = {@see PerfHealth::APDEX_THRESHOLD_MS}, null with no traffic
	 */
	public function __construct(
		public PerfWindow $window,
		public PerfTimePoint $total,
		public float $maxMs,
		public ?float $apdex,
	) {
	}

	public function isEmpty(): bool
	{
		return $this->total->hits === 0;
	}
}
