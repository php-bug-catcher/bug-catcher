<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Report\Dto;

use BugCatcher\Enum\PerfUnit;
use DateTimeImmutable;

/**
 * What the detail page of a {@see \BugCatcher\Entity\RecordPerformance} draws: the route's own
 * series around the time it regressed, with the numbers the record was written from.
 *
 * The baseline is carried so the chart can draw it as a reference line - "this is what normal
 * was" - which is the difference between a chart that shows a spike and a chart that explains why
 * somebody was told about it.
 */
final readonly class PathDetailReport
{
	public function __construct(
		public PerfTimeSeries $series,
		public string $path,
		public string $metric,
		public PerfUnit $unit,
		public ?float $baseline,
		public ?float $observed,
		public DateTimeImmutable $windowAt,
	) {
	}
}
