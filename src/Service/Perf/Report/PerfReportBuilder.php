<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Report;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Enum\PerfTopPathGroup;
use BugCatcher\Enum\PerfTopPathSort;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\Histogram\PercentileEstimator;
use BugCatcher\Service\Perf\PerfWindow;
use BugCatcher\Service\Perf\Report\Dto\LatencyBands;
use BugCatcher\Service\Perf\Report\Dto\PathDetailReport;
use BugCatcher\Service\Perf\Report\Dto\PerfTimePoint;
use BugCatcher\Service\Perf\Report\Dto\PerfTimeSeries;
use BugCatcher\Service\Perf\Report\Dto\TopPathRow;
use BugCatcher\Service\Perf\Report\Dto\TopPathsReport;
use BugCatcher\Service\Perf\WindowAggregate;
use DateInterval;
use DateTimeImmutable;

/**
 * Everything the dashboard reads, as readonly objects.
 *
 * The components call this and nothing else: no SQL in a Twig component, and no arithmetic in a
 * template. What comes back is already in the units a chart axis speaks - milliseconds per hit,
 * bytes per hit, a ratio - so the same numbers can be drawn, printed and compared without being
 * converted twice differently.
 */
final readonly class PerfReportBuilder
{
	/** How far either side of a regression its detail chart looks, at minute resolution. */
	private const string DETAIL_MINUTES_AROUND = 'PT1H';

	/** And how far when the minutes are gone and only hours are left. */
	private const string DETAIL_HOURS_AROUND = 'PT12H';

	public const int DEFAULT_TOP_PATHS = 20;

	/**
	 * The windows a panel offers, in hours.
	 *
	 * It stops at a week because that is where the series stops being readable *and* affordable:
	 * seven days of hours is 168 points, and a chart is inline SVG - one bar per point per
	 * series, in the page.
	 */
	public const array WINDOW_HOURS = [1 => '1 h', 6 => '6 h', 24 => '24 h', 168 => '7 d'];

	public function __construct(
		private PerfBucketRepository $repository,
		private PercentileEstimator $percentiles,
		private GranularityResolver $granularity,
	) {
	}

	/**
	 * One point per bucket between the two instants, quiet buckets included.
	 *
	 * @param Project|null $project null is every project at once - what the dashboard shows
	 *     until somebody picks one
	 * @param string|null $path one route rather than the whole project
	 */
	public function timeSeries(
		?Project $project,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
		?string $path = null,
	): PerfTimeSeries {
		return $this->seriesOver($this->granularity->window($from, $to), $project, $path);
	}

	/**
	 * The heaviest rows of the window, grouped and sorted the way the page asked.
	 *
	 * Without a project every row is named after the one it belongs to, because two
	 * applications both have a `/login` and one merged row would be a row about nothing.
	 */
	public function topPaths(
		?Project $project,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
		PerfTopPathGroup $group = PerfTopPathGroup::Path,
		PerfTopPathSort $sort = PerfTopPathSort::Hits,
		int $limit = self::DEFAULT_TOP_PATHS,
	): TopPathsReport {
		$window = $this->granularity->window($from, $to);

		// one more than asked for, which is how the report knows the list was cut
		$aggregates = $this->repository->aggregateGrouped(
			$group,
			$window->granularity,
			$project,
			$window->from,
			$window->to,
			$limit + 1,
		);

		$truncated = count($aggregates) > $limit;
		$rows      = array_map($this->row(...), array_values($aggregates));

		usort($rows, static fn(TopPathRow $a, TopPathRow $b): int => $sort->valueOf($b) <=> $sort->valueOf($a));

		return new TopPathsReport($window, $group, $sort, array_slice($rows, 0, $limit), $truncated);
	}

	/**
	 * The route of a regression around the time it was found.
	 *
	 * Minutes if they are still there, because that is the resolution the spike is visible at,
	 * and hours otherwise: minute rows are kept for a week by default and a record outlives them,
	 * so a chart that insisted on minutes would say the route had been idle.
	 */
	public function pathDetail(RecordPerformance $record): PathDetailReport
	{
		$at      = $record->getWindowAt() ?? $record->getDate();
		$project = $record->getProject();
		$path    = (string)$record->getPath();

		$series = $this->seriesOver(
			$this->around($at, PerfGranularity::Minute, new DateInterval(self::DETAIL_MINUTES_AROUND)),
			$project,
			$path,
		);

		if ($series->isEmpty()) {
			$series = $this->seriesOver(
				$this->around($at, PerfGranularity::Hour, new DateInterval(self::DETAIL_HOURS_AROUND)),
				$project,
				$path,
			);
		}

		return new PathDetailReport(
			$series,
			$path,
			(string)$record->getMetric(),
			$record->getUnit() ?? PerfUnit::Milliseconds,
			$record->getBaseline(),
			$record->getObserved(),
			$at,
		);
	}

	private function seriesOver(PerfWindow $window, ?Project $project, ?string $path): PerfTimeSeries
	{
		$aggregates = $this->repository->aggregateByBucket(
			$window->granularity,
			$project,
			$window->from,
			$window->to,
			$path === null ? null : PerfBucket::hashPath($path),
		);

		$points = [];
		foreach ($window->boundaries() as $boundary) {
			$aggregate = $aggregates[$boundary->format('Y-m-d H:i:s')] ?? null;

			$points[] = $aggregate === null
				? PerfTimePoint::empty($boundary)
				: $this->point($boundary, $aggregate);
		}

		return new PerfTimeSeries($window, $points);
	}

	private function point(DateTimeImmutable $bucketAt, WindowAggregate $aggregate): PerfTimePoint
	{
		$avgMs  = $this->perHit($aggregate->sumDuration * 1000, $aggregate->hits);
		$userMs = $this->perHit($aggregate->sumUser * 1000, $aggregate->hits);
		$sysMs  = $this->perHit($aggregate->sumSys * 1000, $aggregate->hits);

		return new PerfTimePoint(
			$bucketAt,
			$aggregate->hits,
			$avgMs,
			$this->percentiles->p95($aggregate->durationHistogram),
			$userMs,
			$sysMs,
			// CPU time is measured by getrusage and wallclock by microtime, so on a busy machine
			// the two can disagree by a hair; the band is what is left, never less than nothing
			max(0.0, $avgMs - $userMs - $sysMs),
			$this->perHit((float)$aggregate->sumMem, $aggregate->hits),
			$aggregate->maxMem,
			$aggregate->ok(),
			$aggregate->clientErrors,
			$aggregate->serverErrors,
			LatencyBands::fromHistogram($aggregate->durationHistogram),
		);
	}

	private function row(WindowAggregate $aggregate): TopPathRow
	{
		return new TopPathRow(
			$aggregate->label,
			$aggregate->hits,
			$aggregate->sumDuration * 1000,
			$aggregate->sumUser * 1000,
			$aggregate->sumSys * 1000,
			$aggregate->sumMem,
			$aggregate->maxMem,
			$this->percentiles->p95($aggregate->durationHistogram),
			$aggregate->hits === 0 ? 0.0 : $aggregate->errors() / $aggregate->hits,
		);
	}

	private function around(DateTimeImmutable $at, PerfGranularity $granularity, DateInterval $half): PerfWindow
	{
		$centre = $granularity->floor($at);

		return new PerfWindow($centre->sub($half), $centre->add($half), $granularity);
	}

	private function perHit(float $total, int $hits): float
	{
		return $hits === 0 ? 0.0 : $total / $hits;
	}
}
