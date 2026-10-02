<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection\Extractor;

use BugCatcher\Enum\PerfMetric;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Service\Perf\Detection\MetricExtractorInterface;
use BugCatcher\Service\Perf\Detection\PathWindowStats;

/**
 * Mean wallclock per request.
 *
 * Coarser than {@see P95MetricExtractor} and worth having anyway: a route whose every request got
 * slower moves the mean, and on low-traffic routes a percentile estimated from a handful of hits
 * says less than the average does.
 */
final readonly class AvgMetricExtractor implements MetricExtractorInterface
{
	public function name(): string
	{
		return PerfMetric::Avg->value;
	}

	public function unit(): PerfUnit
	{
		return PerfUnit::Milliseconds;
	}

	public function extract(PathWindowStats $stats): ?float
	{
		if ($stats->hits === 0) {
			return null;
		}

		return $stats->sumDuration / $stats->hits * 1000;
	}
}
