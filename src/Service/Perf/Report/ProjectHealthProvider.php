<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Report;

use BugCatcher\Entity\Project;
use BugCatcher\Service\Perf\Report\Dto\PerfHealth;
use DateTimeImmutable;

/**
 * One read of a project's window, shared by every status component on its row.
 *
 * The dashboard draws a row per project and each row asks three components for a number out of
 * the same window. Without this, a wall of twenty projects is sixty queries for twenty answers -
 * the row is the unit of reading, not the cell.
 *
 * The cache lives for the request, which is exactly as long as one render of the list. A live
 * component that refreshes on its own gets a fresh request and so a fresh read, which is what
 * anybody watching a dashboard expects.
 *
 * Deliberately not `readonly`, unlike everything else under `Report/`: the memo is the point.
 */
final class ProjectHealthProvider
{
	/** @var array<string, PerfHealth> */
	private array $cache = [];

	public function __construct(private readonly PerfReportBuilder $report)
	{
	}

	/** @param int $hours how far back the row looks; the key of the cache as much as the window */
	public function forProject(?Project $project, int $hours): PerfHealth
	{
		$key = ($project?->getId()?->toRfc4122() ?? 'all') . ':' . $hours;

		return $this->cache[$key] ??= $this->report->health(
			$project,
			new DateTimeImmutable("-{$hours} hours"),
			new DateTimeImmutable(),
		);
	}
}
