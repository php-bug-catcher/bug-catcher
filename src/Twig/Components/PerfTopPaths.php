<?php

declare(strict_types=1);

namespace BugCatcher\Twig\Components;

use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfTopPathGroup;
use BugCatcher\Enum\PerfTopPathSort;
use BugCatcher\Service\Perf\Report\Dto\TopPathRow;
use BugCatcher\Service\Perf\Report\Dto\TopPathsReport;
use BugCatcher\Service\Perf\Report\PerfRangeResolver;
use BugCatcher\Service\Perf\Report\PerfReportBuilder;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * What phptop prints, over stored buckets: the heaviest rows of a window, with its command line
 * flags as controls.
 *
 * Every control is a writable `LiveProp`, so changing one re-reads the report and nothing else -
 * no JavaScript of our own, no client-side sorting of a list the server already cut. The one
 * exception is the date and time of the window, which needs two widgets a browser does not have:
 * see `assets/controllers/perf-range_controller.js`.
 *
 * `path` here only marks a row, rather than filtering to it - the table's whole job is comparing a
 * route against the others, and a table of one row compares nothing. It is `PerfOverview` that
 * narrows its charts to the route.
 *
 * Opt-in: add `PerfTopPaths` to `bug_catcher.dashboard_components`.
 */
#[AsLiveComponent]
final class PerfTopPaths
{
	use DefaultActionTrait;
	use PerfRangeProps;

	#[LiveProp]
	public ?Project $project = null;

	#[LiveProp(writable: true)]
	public PerfTopPathSort $sort = PerfTopPathSort::Hits;

	#[LiveProp(writable: true)]
	public PerfTopPathGroup $group = PerfTopPathGroup::Path;

	/** Averages per request rather than totals - the same numbers, the other question. */
	#[LiveProp(writable: true)]
	public bool $perHit = false;

	#[LiveProp(writable: true)]
	public int $limit = PerfReportBuilder::DEFAULT_TOP_PATHS;

	private ?TopPathsReport $result = null;

	public function __construct(
		private readonly PerfReportBuilder $report,
		private readonly PerfRangeResolver $rangeResolver,
	) {
	}

	/**
	 * With no project selected this is every project at once, and each row is named after the
	 * application it belongs to - two of them both have a `/login`.
	 *
	 * Memoised, because the template reads it and so does {@see isPathListed()}, and it is a
	 * `GROUP BY` over the whole window.
	 */
	public function getReport(): TopPathsReport
	{
		if ($this->result !== null) {
			return $this->result;
		}

		$range = $this->getRange();

		return $this->result = $this->report->topPaths(
			$this->project,
			$range->from,
			$range->to,
			$this->group,
			$this->sort,
			$this->limit,
		);
	}

	/**
	 * Whether the route the page was opened for is one of the rows below.
	 *
	 * It often is not: the report cuts the window to its busiest rows, and a route slow enough to
	 * regress is frequently not a route called often enough to be heavy. The template says so
	 * rather than leaving the link's `#perf-path-…` fragment scrolling to nothing.
	 */
	public function isPathListed(): bool
	{
		if ($this->path === null || !$this->isGroupedByPath()) {
			return false;
		}

		foreach ($this->getReport()->rows as $row) {
			if ($row->label === $this->path) {
				return true;
			}
		}

		return false;
	}

	/** Whether this row is the one the page was opened for, and should be marked as such. */
	public function isHighlighted(TopPathRow $row): bool
	{
		return $this->path !== null && $this->isGroupedByPath() && $row->label === $this->path;
	}

	/**
	 * Whether the rows are routes at all.
	 *
	 * Grouped by machine or vhost a `label` is not a path, so neither the `#perf-path-…` anchor nor
	 * matching one against `path` means anything - and an id that looks like a route but is a
	 * hostname is worse than no id.
	 */
	public function isGroupedByPath(): bool
	{
		return $this->group === PerfTopPathGroup::Path;
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

	protected function ranges(): PerfRangeResolver
	{
		return $this->rangeResolver;
	}
}
