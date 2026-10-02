<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf;

/**
 * Buckets added up over a window, for one slice of them.
 *
 * What a slice is depends on who asked. Detection groups by route, so `label` is the path and
 * `key` is its hash; the top-paths table can group by vhost or by machine instead, and the time
 * series groups by bucket, where the label is the timestamp. One type for all three because the
 * arithmetic behind them is identical - the grouping happens in SQL - and a second type with the
 * same eleven fields would only invite the two to drift apart.
 *
 * Deliberately not a {@see \BugCatcher\Entity\PerfBucket}: a window is not a bucket, it spans
 * machines and vhosts, and it has no granularity of its own because the rows under it may be
 * minutes or hours depending on how far back it reaches.
 *
 * Durations are **seconds** and memory is **bytes**, as stored. Turning them into the unit a
 * metric or an axis speaks is the caller's job.
 */
final readonly class WindowAggregate
{
	/**
	 * @param string $label what this slice is called - a route, a vhost, a machine, a timestamp
	 * @param string $key what identifies it, which for a route is
	 *     {@see \BugCatcher\Entity\PerfBucket::hashPath()}
	 * @param list<int> $durationHistogram counts per bin of
	 *     {@see \BugCatcher\Service\Perf\Histogram\HistogramBins}
	 * @param array<string, float> $extra whatever the monitored application merged into
	 *     `$GLOBALS['_bcperf_extra']`, summed over the window - the only thing a custom metric
	 *     extractor has that the built-in four do not
	 */
	public function __construct(
		public string $label,
		public string $key,
		public int $hits,
		public float $sumDuration,
		public float $sumUser,
		public float $sumSys,
		public float $maxDuration,
		public int $sumMem,
		public int $maxMem,
		public int $clientErrors,
		public int $serverErrors,
		public array $durationHistogram,
		public array $extra = [],
	) {
	}

	public function errors(): int
	{
		return $this->clientErrors + $this->serverErrors;
	}

	/** Requests that were neither 4xx nor 5xx. The collector counts no finer than that. */
	public function ok(): int
	{
		return max(0, $this->hits - $this->errors());
	}
}
