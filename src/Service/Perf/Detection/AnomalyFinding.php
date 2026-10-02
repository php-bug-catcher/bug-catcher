<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection;

use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfUnit;
use DateTimeImmutable;

/**
 * One route whose metric crossed a threshold - what a detector yields and what
 * {@see RecordPerformanceWriter} turns into a {@see \BugCatcher\Entity\RecordPerformance}.
 *
 * The unit travels with the numbers because the metric may be one an application registered, and
 * nothing downstream can look it up once the record is stored.
 */
final readonly class AnomalyFinding
{
	public function __construct(
		public Project $project,
		public string $path,
		public string $metric,
		public PerfUnit $unit,
		public float $baseline,
		public float $observed,
		public DateTimeImmutable $windowAt,
	) {
	}
}
