<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection\Extractor;

use BugCatcher\Enum\PerfMetric;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Service\Perf\Detection\MetricExtractorInterface;
use BugCatcher\Service\Perf\WindowAggregate;

/**
 * The share of requests that answered 4xx or 5xx.
 *
 * Both kinds together: a route that starts answering 404 because a slug changed is as much a
 * regression as one that starts answering 500, and the collector counts them apart only so that
 * the dashboard can draw them apart.
 */
final readonly class ErrorRateMetricExtractor implements MetricExtractorInterface
{
	public function name(): string
	{
		return PerfMetric::ErrorRate->value;
	}

	public function unit(): PerfUnit
	{
		return PerfUnit::Ratio;
	}

	public function extract(WindowAggregate $window): ?float
	{
		if ($window->hits === 0) {
			return null;
		}

		return $window->errors() / $window->hits;
	}
}
