<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection;

/**
 * Everything one route did over one window, with the buckets behind it already added up.
 *
 * Deliberately not a {@see \BugCatcher\Entity\PerfBucket}: a window is not a bucket. It spans
 * machines and vhosts on purpose - "checkout got slower" is a statement about the route, and a
 * regression that only shows on one machine still shows here - and it has no granularity, because
 * the rows it was built from may be minutes or hours depending on how far back the window reaches.
 *
 * Durations are **seconds** and memory is **bytes**, as stored. Turning them into the unit a
 * metric speaks is the extractor's job.
 */
final readonly class PathWindowStats
{
	/**
	 * @param list<int> $durationHistogram counts per bin of
	 *     {@see \BugCatcher\Service\Perf\Histogram\HistogramBins}
	 * @param array<string, float> $extra whatever the monitored application merged into
	 *     `$GLOBALS['_bcperf_extra']`, summed over the window - the only thing a custom metric
	 *     extractor has that the built-in four do not
	 */
	public function __construct(
		public string $path,
		public string $pathHash,
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
}
