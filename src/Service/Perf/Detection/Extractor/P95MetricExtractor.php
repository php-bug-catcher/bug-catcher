<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection\Extractor;

use BugCatcher\Enum\PerfMetric;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Service\Perf\Detection\MetricExtractorInterface;
use BugCatcher\Service\Perf\WindowAggregate;
use BugCatcher\Service\Perf\Histogram\PercentileEstimator;

/**
 * The 95th percentile of the window, estimated from the stored histogram.
 *
 * The default metric, and the reason the histogram is stored at all: a mean hides exactly the
 * regression one is looking for - a tail that grew - and a maximum reacts to a single outlier.
 */
final readonly class P95MetricExtractor implements MetricExtractorInterface
{
	public function __construct(private PercentileEstimator $percentiles)
	{
	}

	public function name(): string
	{
		return PerfMetric::P95->value;
	}

	public function unit(): PerfUnit
	{
		return PerfUnit::Milliseconds;
	}

	public function extract(WindowAggregate $window): ?float
	{
		return $this->percentiles->p95($window->durationHistogram);
	}
}
