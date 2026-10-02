<?php

declare(strict_types=1);

namespace BugCatcher\Twig\Components;

use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfTopPathGroup;
use BugCatcher\Enum\PerfTopPathSort;
use BugCatcher\Service\Perf\Report\Dto\TopPathsReport;
use BugCatcher\Service\Perf\Report\PerfReportBuilder;
use DateTimeImmutable;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * What phptop prints, over stored buckets: the heaviest rows of a window, with its command line
 * flags as controls.
 *
 * Every control is a writable `LiveProp`, so changing one re-reads the report and nothing else -
 * no JavaScript of our own, no client-side sorting of a list the server already cut.
 *
 * Opt-in: add `PerfTopPaths` to `bug_catcher.dashboard_components`.
 */
#[AsLiveComponent]
final class PerfTopPaths
{
	use DefaultActionTrait;

	#[LiveProp]
	public ?Project $project = null;

	/** How far back the table reaches. The report picks the bucket width from it. */
	#[LiveProp(writable: true)]
	public int $hours = 1;

	#[LiveProp(writable: true)]
	public PerfTopPathSort $sort = PerfTopPathSort::Hits;

	#[LiveProp(writable: true)]
	public PerfTopPathGroup $group = PerfTopPathGroup::Path;

	/** Averages per request rather than totals - the same numbers, the other question. */
	#[LiveProp(writable: true)]
	public bool $perHit = false;

	#[LiveProp(writable: true)]
	public int $limit = PerfReportBuilder::DEFAULT_TOP_PATHS;

	public function __construct(private readonly PerfReportBuilder $report)
	{
	}

	/**
	 * Null until somebody picks a project. The dashboard shows every project by default, and a
	 * table of several projects' routes added together would be a table of nothing: two
	 * applications both have a `/login`.
	 */
	public function getReport(): ?TopPathsReport
	{
		if ($this->project === null) {
			return null;
		}

		return $this->report->topPaths(
			$this->project,
			new DateTimeImmutable("-{$this->hours} hours"),
			new DateTimeImmutable(),
			$this->group,
			$this->sort,
			$this->limit,
		);
	}

	/** @return list<PerfTopPathSort> */
	public function getSorts(): array
	{
		return PerfTopPathSort::cases();
	}

	/** @return list<PerfTopPathGroup> */
	public function getGroups(): array
	{
		return PerfTopPathGroup::cases();
	}

	/** @return array<int, string> the windows the control offers, in hours */
	public function getWindows(): array
	{
		return PerfReportBuilder::WINDOW_HOURS;
	}
}
