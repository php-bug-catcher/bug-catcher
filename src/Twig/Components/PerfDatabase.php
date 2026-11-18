<?php

declare(strict_types=1);

namespace BugCatcher\Twig\Components;

use BugCatcher\Entity\Project;
use BugCatcher\Service\Perf\Chart\DatabaseChartBuilder;
use BugCatcher\Service\Perf\Report\Dto\DatabaseReport;
use BugCatcher\Service\Perf\Report\Dto\PerfTimeSeries;
use BugCatcher\Service\Perf\Report\Dto\TopPathRow;
use BugCatcher\Service\Perf\Report\PerfRangeResolver;
use BugCatcher\Service\Perf\Report\PerfReportBuilder;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The database half of a request: how many queries it ran and how long they took.
 *
 * These numbers do not come from the hook - the hook cannot see a query. They come from
 * `php-bug-catcher/perf-collector-bundle`, whose DBAL middleware counts and times every query and
 * merges two numbers into `$GLOBALS['_bcperf_extra']` on `kernel.terminate`; the collector sums
 * them per bucket and the server stores them in `perf_bucket_extra`. So this panel is the one
 * part of the performance dashboard that an installation can have no data for even with the
 * collector running perfectly - see {@see isCollected()}, and the message the template shows.
 *
 * Two questions, and the second is the reason for the first: `PerfOverview` draws waiting as one
 * band, and waiting is where a slow application spends its time. This says how much of that band
 * is the database, over time and per route.
 *
 * The arithmetic is {@see DatabaseReport}'s, shared with the MCP tools; this only draws it.
 *
 * Opt-in: add `PerfDatabase` to `bug_catcher.dashboard_components`.
 */
#[AsLiveComponent]
final class PerfDatabase
{
	use DefaultActionTrait;
	use PerfRangeProps;

	/** How many routes the table under the charts lists. A screenful, like every other panel. */
	public const int ROUTES = PerfReportBuilder::DATABASE_ROUTES;

	#[LiveProp]
	public ?Project $project = null;

	private ?DatabaseReport $result = null;

	public function __construct(
		private readonly PerfReportBuilder $report,
		private readonly PerfRangeResolver $rangeResolver,
		private readonly DatabaseChartBuilder $charts,
	) {
	}

	public function getReport(): DatabaseReport
	{
		if ($this->result !== null) {
			return $this->result;
		}

		$range = $this->getRange();

		return $this->result = $this->report->database(
			$this->project,
			$range->from,
			$range->to,
			$this->path,
			self::ROUTES,
		);
	}

	public function getSeries(): PerfTimeSeries
	{
		return $this->getReport()->series;
	}

	/** See {@see DatabaseReport::isCollected()}. */
	public function isCollected(): bool
	{
		return $this->getReport()->isCollected();
	}

	/** @return array{queries: string, time: string} */
	public function getCharts(): array
	{
		$series = $this->getSeries();

		if ($series->isEmpty() || !$this->isCollected()) {
			return ['queries' => '', 'time' => ''];
		}

		return $this->charts->build($series);
	}

	public function getQueriesPerHit(): float
	{
		return $this->getReport()->queriesPerHit();
	}

	public function getDbMsPerHit(): float
	{
		return $this->getReport()->dbMsPerHit();
	}

	public function getQueries(): float
	{
		return $this->getReport()->queries();
	}

	public function getShareOfWallclock(): float
	{
		return $this->getReport()->shareOfWallclock();
	}

	/** @return list<TopPathRow> */
	public function getRoutes(): array
	{
		return $this->getReport()->routes;
	}

	public function shareOf(TopPathRow $row): float
	{
		return DatabaseReport::shareOf($row);
	}

	public function queriesPerHitOf(TopPathRow $row): ?float
	{
		return DatabaseReport::queriesPerHitOf($row);
	}

	public function dbMsPerHitOf(TopPathRow $row): ?float
	{
		return DatabaseReport::dbMsPerHitOf($row);
	}

	protected function ranges(): PerfRangeResolver
	{
		return $this->rangeResolver;
	}
}
