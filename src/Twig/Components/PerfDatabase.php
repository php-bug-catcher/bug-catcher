<?php

declare(strict_types=1);

namespace BugCatcher\Twig\Components;

use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfTopPathGroup;
use BugCatcher\Enum\PerfTopPathSort;
use BugCatcher\Service\Perf\Chart\DatabaseChartBuilder;
use BugCatcher\Service\Perf\Report\Dto\PerfTimePoint;
use BugCatcher\Service\Perf\Report\Dto\PerfTimeSeries;
use BugCatcher\Service\Perf\Report\Dto\TopPathRow;
use BugCatcher\Service\Perf\Report\PerfReportBuilder;
use BugCatcher\Service\Perf\SqlMetrics;
use DateTimeImmutable;
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
 * Opt-in: add `PerfDatabase` to `bug_catcher.dashboard_components`.
 */
#[AsLiveComponent]
final class PerfDatabase
{
	use DefaultActionTrait;

	/** How many routes the table under the charts lists. A screenful, like every other panel. */
	public const int ROUTES = 10;

	#[LiveProp]
	public ?Project $project = null;

	#[LiveProp(writable: true)]
	public int $hours = 1;

	private ?PerfTimeSeries $series = null;

	/** @var list<TopPathRow>|null */
	private ?array $routes = null;

	public function __construct(
		private readonly PerfReportBuilder $report,
		private readonly DatabaseChartBuilder $charts,
	) {
	}

	/** One read of the window, shared by the charts and the totals above them. */
	public function getSeries(): PerfTimeSeries
	{
		return $this->series ??= $this->report->timeSeries(
			$this->project,
			new DateTimeImmutable("-{$this->hours} hours"),
			new DateTimeImmutable(),
		);
	}

	/**
	 * Whether anything in this window counted its queries at all.
	 *
	 * An empty panel is a question - is the application fast, or is nobody looking? - so the
	 * template answers it, and this is what it answers from. Deliberately not "are there hits":
	 * a window with plenty of traffic and no `sq` means the collector bundle is missing, which
	 * is a different problem from a quiet night.
	 */
	public function isCollected(): bool
	{
		foreach ($this->getSeries()->points as $point) {
			if (isset($point->extra[SqlMetrics::QUERIES]) || isset($point->extra[SqlMetrics::SECONDS])) {
				return true;
			}
		}

		return false;
	}

	/** @return array{queries: string, time: string} two SVGs, or empty strings */
	public function getCharts(): array
	{
		$series = $this->getSeries();

		if ($series->isEmpty() || !$this->isCollected()) {
			return ['queries' => '', 'time' => ''];
		}

		return $this->charts->build($series);
	}

	/** Queries per request over the whole window, not the mean of the per-bucket means. */
	public function getQueriesPerHit(): float
	{
		return $this->perHit($this->total(SqlMetrics::QUERIES));
	}

	public function getDbMsPerHit(): float
	{
		return $this->perHit($this->total(SqlMetrics::SECONDS)) * 1000;
	}

	public function getQueries(): float
	{
		return $this->total(SqlMetrics::QUERIES);
	}

	/**
	 * What share of the window's wallclock was spent in the database, 0 to 1.
	 *
	 * The number worth putting at the top of the panel: "12 queries a request" means nothing
	 * without knowing whether they cost 2 % of the response or 80 % of it.
	 */
	public function getShareOfWallclock(): float
	{
		$wallMs = array_sum(array_map(
			static fn(PerfTimePoint $point): float => $point->avgMs * $point->hits,
			$this->getSeries()->points,
		));

		return $wallMs <= 0.0 ? 0.0 : min(1.0, $this->total(SqlMetrics::SECONDS) * 1000 / $wallMs);
	}

	/**
	 * The routes of the window that spent the longest in the database.
	 *
	 * Sorted here rather than in SQL, and the consequence is worth stating: the report cuts the
	 * window to its busiest routes first, so this is the heaviest *of the busiest* - the same
	 * caveat the p95 column of `PerfTopPaths` carries, and for the same reason. A route called
	 * twice an hour that runs ten thousand queries each time is not on this list; the regression
	 * detector is what finds that one.
	 *
	 * @return list<TopPathRow>
	 */
	public function getRoutes(): array
	{
		if ($this->routes !== null) {
			return $this->routes;
		}

		$rows = $this->report->topPaths(
			$this->project,
			new DateTimeImmutable("-{$this->hours} hours"),
			new DateTimeImmutable(),
			PerfTopPathGroup::Path,
			PerfTopPathSort::Hits,
		)->rows;

		$rows = array_values(array_filter(
			$rows,
			static fn(TopPathRow $row): bool => $row->extraTotal(SqlMetrics::SECONDS) !== null,
		));

		usort($rows, static fn(TopPathRow $a, TopPathRow $b): int
			=> $b->extraTotal(SqlMetrics::SECONDS) <=> $a->extraTotal(SqlMetrics::SECONDS));

		return $this->routes = array_slice($rows, 0, self::ROUTES);
	}

	/** What share of one route's own wallclock was the database, 0 to 1. */
	public function shareOf(TopPathRow $row): float
	{
		$dbMs = ($row->extraTotal(SqlMetrics::SECONDS) ?? 0.0) * 1000;

		return $row->totalMs <= 0.0 ? 0.0 : min(1.0, $dbMs / $row->totalMs);
	}

	public function queriesPerHitOf(TopPathRow $row): ?float
	{
		return $row->extraPerHit(SqlMetrics::QUERIES);
	}

	public function dbMsPerHitOf(TopPathRow $row): ?float
	{
		$perHit = $row->extraPerHit(SqlMetrics::SECONDS);

		return $perHit === null ? null : $perHit * 1000;
	}

	/** @return array<int, string> the windows the control offers, in hours */
	public function getWindows(): array
	{
		return PerfReportBuilder::WINDOW_HOURS;
	}

	private function total(string $metric): float
	{
		return array_sum(array_map(
			static fn(PerfTimePoint $point): float => $point->extra[$metric] ?? 0.0,
			$this->getSeries()->points,
		));
	}

	private function perHit(float $total): float
	{
		$hits = $this->getSeries()->hits();

		return $hits === 0 ? 0.0 : $total / $hits;
	}
}
