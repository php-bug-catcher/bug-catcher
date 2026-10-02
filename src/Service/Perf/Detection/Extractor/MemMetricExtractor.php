<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection\Extractor;

use BugCatcher\Enum\PerfMetric;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Service\Perf\Detection\MetricExtractorInterface;
use BugCatcher\Service\Perf\WindowAggregate;

/**
 * Peak memory of the average request.
 *
 * Per hit rather than cumulative: the total only says how busy the route was, while this one
 * catches the query that started loading the whole table.
 */
final readonly class MemMetricExtractor implements MetricExtractorInterface
{
	public function name(): string
	{
		return PerfMetric::Mem->value;
	}

	public function unit(): PerfUnit
	{
		return PerfUnit::Bytes;
	}

	public function extract(WindowAggregate $window): ?float
	{
		if ($window->hits === 0) {
			return null;
		}

		return $window->sumMem / $window->hits;
	}
}
