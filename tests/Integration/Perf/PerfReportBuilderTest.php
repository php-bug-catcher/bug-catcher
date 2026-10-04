<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Perf;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Enum\PerfTopPathGroup;
use BugCatcher\Enum\PerfTopPathSort;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\Histogram\PercentileEstimator;
use BugCatcher\Service\Perf\Report\GranularityResolver;
use BugCatcher\Service\Perf\Report\PerfReportBuilder;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\Factories;

class PerfReportBuilderTest extends KernelTestCase
{
	use Factories;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne()->_real();
	}

	/**
	 * Built by hand: until the Twig components consume it the compiler inlines it away, so there
	 * is nothing to fetch. That it is autowired is covered where it matters, by the component
	 * tests.
	 */
	private function builder(): PerfReportBuilder
	{
		return new PerfReportBuilder(
			self::getContainer()->get(PerfBucketRepository::class),
			new PercentileEstimator(),
			new GranularityResolver(),
		);
	}

	public function testATimeSeriesHasOnePointPerBucketOfTheWindow(): void
	{
		$this->minute('14:00', hits: 10, msPerHit: 80);
		$this->minute('14:02', hits: 20, msPerHit: 300);

		$series = $this->builder()->timeSeries(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:05:00'),
		);

		$this->assertSame(PerfGranularity::Minute, $series->window->granularity);
		$this->assertCount(5, $series->points);
		$this->assertSame(
			['14:00', '14:01', '14:02', '14:03', '14:04'],
			$series->labels(),
		);
		$this->assertSame([10, 0, 20, 0, 0], $series->series(static fn($point): int => $point->hits));
	}

	/**
	 * A chart that omits the quiet buckets draws a line straight across an outage, which is the
	 * opposite of what the page is for.
	 */
	public function testABucketNothingHappenedInIsAPointOfItsOwn(): void
	{
		$this->minute('14:00', hits: 10, msPerHit: 80);

		$quiet = $this->builder()->timeSeries(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:02:00'),
		)->points[1];

		$this->assertSame(0, $quiet->hits);
		$this->assertSame(0.0, $quiet->avgMs);
		$this->assertNull($quiet->p95Ms);
		$this->assertSame(0, $quiet->bands->total());
	}

	/** Milliseconds per hit, because that is what an axis says and what a person compares. */
	public function testAPointIsWhatOneRequestCostOnAverage(): void
	{
		$this->minute('14:00', hits: 10, msPerHit: 300, userMs: 120, sysMs: 30, memPerHit: 2_097_152, errors: 2);

		$point = $this->builder()->timeSeries(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:01:00'),
		)->points[0];

		$this->assertSame(10, $point->hits);
		$this->assertSame(300.0, $point->avgMs);
		$this->assertSame(120.0, $point->userMs);
		$this->assertSame(30.0, $point->sysMs);
		// what was spent waiting on the database, the cache, the network
		$this->assertSame(150.0, $point->waitMs);
		$this->assertSame(2_097_152.0, $point->memPerHit);
		$this->assertSame(8, $point->ok);
		$this->assertSame(2, $point->clientErrors);
		$this->assertSame(0, $point->serverErrors);
		$this->assertGreaterThanOrEqual(250.0, $point->p95Ms);
		$this->assertLessThanOrEqual(500.0, $point->p95Ms);
	}

	public function testThePointsBandsComeStraightOutOfTheHistogram(): void
	{
		$this->minute('14:00', hits: 10, msPerHit: 80);
		$this->minute('14:00', hits: 5, msPerHit: 3000, path: '/slow');

		$bands = $this->builder()->timeSeries(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:01:00'),
		)->points[0]->bands;

		$this->assertSame(10, $bands->under100Ms);
		$this->assertSame(5, $bands->to10s);
		$this->assertSame(15, $bands->total());
	}

	public function testAWiderWindowIsReadInCoarserBuckets(): void
	{
		$this->bucket(PerfGranularity::Hour, '2026-03-10 14:00:00', hits: 600, msPerHit: 100);

		$series = $this->builder()->timeSeries(
			$this->project,
			new DateTimeImmutable('2026-03-10 00:00:00'),
			new DateTimeImmutable('2026-03-11 00:00:00'),
		);

		$this->assertSame(PerfGranularity::Hour, $series->window->granularity);
		$this->assertCount(24, $series->points);
		$this->assertSame(600, $series->points[14]->hits);
	}

	public function testTheSeriesOfOneRouteLeavesTheOthersOut(): void
	{
		$this->minute('14:00', hits: 10, msPerHit: 80);
		$this->minute('14:00', hits: 5, msPerHit: 3000, path: '/slow');

		$series = $this->builder()->timeSeries(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:01:00'),
			'/slow',
		);

		$this->assertSame(5, $series->points[0]->hits);
	}

	public function testTheTopPathsTableIsTheHeaviestRoutesFirst(): void
	{
		$this->minute('14:00', hits: 10, msPerHit: 100, path: '/busy');
		$this->minute('14:00', hits: 2, msPerHit: 3000, path: '/slow');

		$report = $this->builder()->topPaths(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:05:00'),
			PerfTopPathGroup::Path,
			PerfTopPathSort::Hits,
		);

		$this->assertSame(PerfTopPathGroup::Path, $report->group);
		$this->assertSame(['/busy', '/slow'], array_column($report->rows, 'label'));
		$this->assertFalse($report->truncated);

		$busy = $report->rows[0];
		$this->assertSame(10, $busy->hits);
		$this->assertSame(1000.0, $busy->totalMs);
		$this->assertSame(100.0, $busy->msPerHit());
	}

	/** @dataProvider sortOrders */
	public function testTheTableIsSortedByWhateverWasAskedFor(PerfTopPathSort $sort, string $first): void
	{
		$this->minute('14:00', hits: 10, msPerHit: 100, userMs: 90, sysMs: 5, memPerHit: 1_000_000, path: '/busy');
		$this->minute('14:00', hits: 2, msPerHit: 3000, userMs: 10, sysMs: 500, memPerHit: 9_000_000, path: '/slow');

		$report = $this->builder()->topPaths(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:05:00'),
			PerfTopPathGroup::Path,
			$sort,
		);

		$this->assertSame($first, $report->rows[0]->label);
	}

	/** @return array<string, array{PerfTopPathSort, string}> */
	public static function sortOrders(): array
	{
		return [
			'hits'           => [PerfTopPathSort::Hits, '/busy'],
			'total time'     => [PerfTopPathSort::TotalTime, '/slow'],
			'user cpu'       => [PerfTopPathSort::User, '/busy'],
			'system cpu'     => [PerfTopPathSort::Sys, '/slow'],
			'memory per hit' => [PerfTopPathSort::MemPerHit, '/slow'],
			'peak memory'    => [PerfTopPathSort::MaxMem, '/slow'],
			'p95'            => [PerfTopPathSort::P95, '/slow'],
		];
	}

	/**
	 * "Is one of the three machines slow" is a different question from "which route is slow", and
	 * the design keeps server_name and host apart so that it can be asked.
	 */
	public function testTheTableCanBeGroupedByMachineOrVhostInstead(): void
	{
		$this->minute('14:00', hits: 10, msPerHit: 100, path: '/a');
		$this->minute('14:00', hits: 4, msPerHit: 100, path: '/b');
		$this->minute('14:00', hits: 3, msPerHit: 100, path: '/a', serverName: 'web-02');

		$byServer = $this->builder()->topPaths(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:05:00'),
			PerfTopPathGroup::Server,
			PerfTopPathSort::Hits,
		);

		$this->assertSame(['web-01', 'web-02'], array_column($byServer->rows, 'label'));
		$this->assertSame(14, $byServer->rows[0]->hits);
	}

	/** A table that silently shows the top of a list it cut off reads as the whole list. */
	public function testATableThatHadToCutTheListSaysSo(): void
	{
		foreach (range(1, 4) as $route) {
			$this->minute('14:00', hits: $route, msPerHit: 100, path: "/route/{$route}");
		}

		$report = $this->builder()->topPaths(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:05:00'),
			PerfTopPathGroup::Path,
			PerfTopPathSort::Hits,
			limit: 2,
		);

		$this->assertCount(2, $report->rows);
		$this->assertTrue($report->truncated);
		$this->assertSame(['/route/4', '/route/3'], array_column($report->rows, 'label'));
	}

	public function testAnEmptyWindowIsAnEmptyReport(): void
	{
		$series = $this->builder()->timeSeries(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:05:00'),
		);

		$this->assertTrue($series->isEmpty());
		$this->assertCount(5, $series->points);
		$this->assertTrue($this->builder()->topPaths(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:05:00'),
		)->isEmpty());
	}

	/**
	 * The detail page of a regression shows the route at minute resolution around the window it
	 * was found in - that is where the spike is visible - and carries the baseline so the chart
	 * can draw what normal was.
	 */
	public function testTheDetailOfARegressionIsTheRouteAroundTheTimeItHappened(): void
	{
		$at = new DateTimeImmutable('-20 minutes');
		$this->bucket(PerfGranularity::Minute, $at->format('Y-m-d H:i:00'), hits: 10, msPerHit: 3000, path: '/checkout');

		$report = $this->builder()->pathDetail($this->regression('/checkout', $at));

		$this->assertSame('/checkout', $report->path);
		$this->assertSame('p95', $report->metric);
		$this->assertSame(PerfUnit::Milliseconds, $report->unit);
		$this->assertSame(210.0, $report->baseline);
		$this->assertSame(3100.0, $report->observed);
		$this->assertSame(PerfGranularity::Minute, $report->series->window->granularity);
		$this->assertSame(10, $report->series->hits());
	}

	/**
	 * Minute rows are kept for a week, so the chart of an older regression has to fall back to
	 * the hours, which are kept for ninety days. An empty chart would say the route was idle.
	 */
	public function testTheDetailOfAnOlderRegressionFallsBackToHours(): void
	{
		$at = new DateTimeImmutable('-30 days');
		$this->bucket(
			PerfGranularity::Hour,
			$at->format('Y-m-d H:00:00'),
			hits: 600,
			msPerHit: 3000,
			path: '/checkout',
		);

		$report = $this->builder()->pathDetail($this->regression('/checkout', $at));

		$this->assertSame(PerfGranularity::Hour, $report->series->window->granularity);
		$this->assertSame(600, $report->series->hits());
	}

	public function testTheDetailOfARegressionNothingWasKeptForIsStillAChart(): void
	{
		$report = $this->builder()->pathDetail($this->regression('/checkout', new DateTimeImmutable('-400 days')));

		$this->assertTrue($report->series->isEmpty());
		$this->assertNotSame([], $report->series->points);
	}

	/**
	 * A machine with no `getrusage()` - Windows - ships no CPU at all, and the collector leaves
	 * the keys out rather than sending zeroes. Wallclock minus nothing is the whole request, so
	 * without this the waiting band would be 100 % on every Windows route for ever.
	 */
	public function testABucketWithNoCpuAtAllHasNoWaitingBandRatherThanAFullOne(): void
	{
		$this->minute('14:00', hits: 10, msPerHit: 200);

		$point = $this->builder()->timeSeries(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:01:00'),
		)->points[0];

		$this->assertSame(200.0, $point->avgMs, 'the duration is still measured');
		$this->assertNull($point->waitMs);
		$this->assertFalse($point->hasCpu());
	}

	/** And a bucket that did report CPU is untouched by the guard. */
	public function testABucketThatReportedCpuKeepsItsWaitingBand(): void
	{
		$this->minute('14:00', hits: 10, msPerHit: 200, userMs: 40.0, sysMs: 10.0);

		$point = $this->builder()->timeSeries(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:01:00'),
		)->points[0];

		$this->assertTrue($point->hasCpu());
		$this->assertSame(150.0, $point->waitMs);
	}

	/** The same question of the top-paths table, which computes its band per row. */
	public function testARowWithNoCpuHasNoWaitingBandEither(): void
	{
		$this->minute('14:00', hits: 10, msPerHit: 200, path: '/windows');
		$this->minute('14:01', hits: 10, msPerHit: 200, path: '/linux', userMs: 40.0, sysMs: 10.0);

		$rows = [];
		foreach ($this->builder()->topPaths(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:05:00'),
		)->rows as $row) {
			$rows[$row->label] = $row->waitMsPerHit();
		}

		$this->assertNull($rows['/windows']);
		$this->assertSame(150.0, $rows['/linux']);
	}

	/**
	 * Whatever the application counted has to survive the whole read, or the panel built on it
	 * has nothing: the aggregate carried `extra` long before the report DTOs had a field for it,
	 * and the numbers were silently dropped here.
	 */
	public function testWhatTheApplicationCountedReachesTheTimeSeries(): void
	{
		$this->minute('14:00', hits: 10, msPerHit: 80, extra: ['sq' => 120, 'st' => 0.4]);

		$points = $this->builder()->timeSeries(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:01:00'),
		)->points;

		// stored as the bucket's total, offered per request
		$this->assertSame(120.0, $points[0]->extra['sq']);
		$this->assertSame(12.0, $points[0]->extraPerHit('sq'));
		$this->assertEqualsWithDelta(0.04, $points[0]->extraPerHit('st'), 0.0001);
	}

	/** Null and not zero: a bucket nobody instrumented is not a bucket that ran no queries. */
	public function testAMetricTheApplicationNeverSentIsAbsentRatherThanZero(): void
	{
		$this->minute('14:00', hits: 10, msPerHit: 80);

		$points = $this->builder()->timeSeries(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:01:00'),
		)->points;

		$this->assertSame([], $points[0]->extra);
		$this->assertNull($points[0]->extraPerHit('sq'));
	}

	public function testWhatTheApplicationCountedReachesTheTopPathsTable(): void
	{
		$this->minute('14:00', hits: 10, msPerHit: 80, path: '/checkout', extra: ['sq' => 200]);
		$this->minute('14:01', hits: 10, msPerHit: 80, path: '/checkout', extra: ['sq' => 100]);

		$rows = $this->builder()->topPaths(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:05:00'),
		)->rows;

		// summed over the window's buckets, not read off the last one
		$this->assertSame(300.0, $rows[0]->extraTotal('sq'));
		$this->assertSame(15.0, $rows[0]->extraPerHit('sq'));
		$this->assertNull($rows[0]->extraTotal('nothing-sent-this'));
	}

	/**
	 * Across projects the table splits the rows by application, and the extras have to split
	 * with them: grouped only by route, both `/checkout` rows would be handed the sum of both.
	 */
	public function testAcrossProjectsEachApplicationKeepsItsOwnCounts(): void
	{
		$other = ProjectFactory::createOne()->_real();

		$this->minute('14:00', hits: 10, msPerHit: 80, path: '/checkout', extra: ['sq' => 200]);
		$this->minute('14:00', hits: 10, msPerHit: 80, path: '/checkout', extra: ['sq' => 50], project: $other);

		$rows = $this->builder()->topPaths(
			null,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:05:00'),
		)->rows;

		$byLabel = [];
		foreach ($rows as $row) {
			$byLabel[$row->label] = $row->extraTotal('sq');
		}

		$this->assertSame(
			[$this->project->getCode() . ' /checkout' => 200.0, $other->getCode() . ' /checkout' => 50.0],
			$byLabel,
		);
	}

	private function regression(string $path, DateTimeImmutable $windowAt): RecordPerformance
	{
		return new RecordPerformance(
			$this->project,
			$path,
			'p95',
			PerfUnit::Milliseconds,
			210.0,
			3100.0,
			$windowAt,
		);
	}

	private function minute(
		string $time,
		int $hits,
		int $msPerHit,
		float $userMs = 0.0,
		float $sysMs = 0.0,
		int $memPerHit = 0,
		int $errors = 0,
		string $path = '/user/{id}',
		string $serverName = 'web-01',
		array $extra = [],
		?Project $project = null,
	): void {
		$this->bucket(
			PerfGranularity::Minute,
			"2026-03-10 {$time}:00",
			$hits,
			$msPerHit,
			$userMs,
			$sysMs,
			$memPerHit,
			$errors,
			$path,
			$serverName,
			$extra,
			$project,
		);
	}

	/** @param array<string, int|float> $extra */
	private function bucket(
		PerfGranularity $granularity,
		string $bucketAt,
		int $hits,
		int $msPerHit,
		float $userMs = 0.0,
		float $sysMs = 0.0,
		int $memPerHit = 0,
		int $errors = 0,
		string $path = '/user/{id}',
		string $serverName = 'web-01',
		array $extra = [],
		?Project $project = null,
	): void {
		$histogram                                          = HistogramBins::empty();
		$histogram[HistogramBins::binFor((float)$msPerHit)] = $hits;

		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				$granularity,
				new DateTimeImmutable($bucketAt),
				$project ?? $this->project,
				$serverName,
				'www.site.com',
				$path,
				hits: $hits,
				sumDuration: $hits * $msPerHit / 1000,
				sumUser: $hits * $userMs / 1000,
				sumSys: $hits * $sysMs / 1000,
				maxDuration: $msPerHit / 1000,
				sumMem: $hits * $memPerHit,
				maxMem: $memPerHit,
				clientErrors: $errors,
				durationHistogram: $histogram,
				extra: $extra,
			),
		]);
	}
}
