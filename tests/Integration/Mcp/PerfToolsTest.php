<?php

namespace BugCatcher\Tests\Integration\Mcp;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Enum\PerfProfile;
use BugCatcher\Enum\PerfTopPathGroup;
use BugCatcher\Enum\PerfTopPathSort;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Mcp\PerfPresenter;
use BugCatcher\Mcp\Tool\PerfTools;
use BugCatcher\Mcp\ToolArguments;
use BugCatcher\Repository\ProjectRepository;
use BugCatcher\Repository\RecordPerformanceRepository;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\Report\PerfReportBuilder;
use BugCatcher\Service\Perf\SqlMetrics;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordLogFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Exception\ToolCallException;
use ReflectionMethod;
use Zenstruck\Foundry\Test\Factories;

class PerfToolsTest extends KernelTestCase {
	use Factories;

	/** Every bucket below is in the hour starting here, so `at` + one hour reads minutes. */
	private const AT = '2026-03-10T10:00:00';

	private PerfTools $tools;
	private Project $project;

	protected function setUp(): void {
		self::bootKernel();
		$this->tools   = self::getContainer()->get(PerfTools::class);
		$this->project = ProjectFactory::createOne(["code" => "shop", "enabled" => true, "perfEnabled" => true])->_real();
	}

	public function testTheHealthOfAProjectIsItsDashboardRow() {
		$this->bucket('/checkout', hits: 30, msPerHit: 80);
		$this->bucket('/checkout', hits: 10, msPerHit: 800, errors: 4, minute: 5);

		$health = $this->tools->getPerfHealth('shop', 1, self::AT);

		$this->assertSame(
			['from' => '2026-03-10 10:00:00', 'to' => '2026-03-10 11:00:00', 'granularity' => 'minute'],
			$health['window'],
		);
		$this->assertCount(1, $health['projects']);
		$row = $health['projects'][0];
		$this->assertSame('shop', $row['projectCode']);
		$this->assertSame('web', $row['profile']);
		$this->assertSame(40, $row['hits']);
		// thirty satisfied, ten tolerating: (30 + 10/2) / 40
		$this->assertSame(0.875, $row['apdex']);
		$this->assertSame('good', $row['apdexRating']);
		$this->assertSame(0.1, $row['errorRate']);
		$this->assertSame(0, $row['openRegressions']);
	}

	/** Without a project it is the overview: which of them is worth asking about. */
	public function testWithoutAProjectEveryMonitoredProjectIsListed() {
		ProjectFactory::createOne(["code" => "queue", "enabled" => true, "perfEnabled" => true, "perfProfile" => PerfProfile::Worker]);
		ProjectFactory::createOne(["code" => "blog", "enabled" => true, "perfEnabled" => false]);
		ProjectFactory::createOne(["code" => "retired", "enabled" => false, "perfEnabled" => true]);

		$health = $this->tools->getPerfHealth(at: self::AT);

		$this->assertSame(['queue', 'shop'], array_column($health['projects'], 'projectCode'));
		$this->assertSame(['worker', 'web'], array_column($health['projects'], 'profile'));
		// a quiet window is nulls, not zeros: a project that shipped nothing is not fast
		$this->assertNull($health['projects'][0]['p95Ms']);
	}

	public function testTheHealthCountsTheRegressionsStillOpen() {
		$this->regression('/checkout');
		$this->regression('/login')->setStatus('resolved');
		self::getContainer()->get(EntityManagerInterface::class)->flush();

		$this->assertSame(1, $this->tools->getPerfHealth('shop')['projects'][0]['openRegressions']);
	}

	public function testTheTopPathsAreTheHeaviestRoutesFirst() {
		$this->bucket('/login', hits: 5, msPerHit: 50);
		$this->bucket('/checkout', hits: 20, msPerHit: 300, userMs: 100, sysMs: 20, memPerHit: 2048);

		$top = $this->tools->getPerfTopPaths('shop', at: self::AT);

		$this->assertSame(['/checkout', '/login'], array_column($top['rows'], 'label'));
		$this->assertFalse($top['truncated']);
		$this->assertNull($top['note']);

		$checkout = $top['rows'][0];
		$this->assertSame(20, $checkout['hits']);
		$this->assertEquals(6000.0, $checkout['totalMs']);
		$this->assertEquals(300.0, $checkout['avgMs']);
		$this->assertEquals(100.0, $checkout['userCpuMs']);
		$this->assertEquals(180.0, $checkout['waitMs']);
		$this->assertSame(2048, $checkout['memPerHitBytes']);
		// nothing counted queries, which is not the same as a route that runs none
		$this->assertNull($checkout['database']);
	}

	public function testTheSortAndTheGroupAreTheOnesThePageOffers() {
		$this->bucket('/busy', hits: 50, msPerHit: 10, server: 'web-01');
		$this->bucket('/slow', hits: 1, msPerHit: 9000, server: 'web-02');

		$bySlowest = $this->tools->getPerfTopPaths('shop', at: self::AT, sortBy: 'max_time');
		$this->assertSame('/slow', $bySlowest['rows'][0]['label']);

		$byMachine = $this->tools->getPerfTopPaths('shop', at: self::AT, groupBy: 'server');
		$this->assertSame(['web-01', 'web-02'], array_column($byMachine['rows'], 'label'));
	}

	/**
	 * The schema lists the values literally - an attribute argument cannot be computed - so this is
	 * what notices a case added to the enum and not to the tool.
	 */
	public function testTheSchemaOffersEveryValueOfTheEnums() {
		$parameters = (new ReflectionMethod(PerfTools::class, 'getPerfTopPaths'))->getParameters();
		$enumOf     = [];
		foreach ($parameters as $parameter) {
			foreach ($parameter->getAttributes() as $attribute) {
				$enumOf[$parameter->getName()] = $attribute->getArguments()['enum'] ?? null;
			}
		}

		$this->assertSame(array_column(PerfTopPathGroup::cases(), 'value'), $enumOf['groupBy']);
		$this->assertSame(array_column(PerfTopPathSort::cases(), 'value'), $enumOf['sortBy']);
	}

	public function testAnUnknownSortIsRefusedWithTheOnesThereAre() {
		$this->expectException(ToolCallException::class);
		$this->expectExceptionMessage('Use one of: hits, total, user, sys, mem, max_mem, max_time, p95.');

		$this->tools->getPerfTopPaths('shop', sortBy: 'fastest');
	}

	public function testALimitOutsideTheRangeIsRefused() {
		$this->expectException(ToolCallException::class);
		$this->expectExceptionMessage('"limit" has to be between 1 and 100.');

		$this->tools->getPerfTopPaths('shop', limit: 101);
	}

	/** The search for a script: part of its path, whatever the rest of it is. */
	public function testARouteIsFoundByPartOfItsPath() {
		$this->bucket('/cron/import.php', hits: 1, msPerHit: 40000);
		$this->bucket('/cron/export.php', hits: 1, msPerHit: 20000);
		$this->bucket('/checkout', hits: 100, msPerHit: 100);

		$found = $this->tools->getPerfTopPaths('shop', at: self::AT, pathContains: 'cron/');

		$this->assertEqualsCanonicalizing(['/cron/import.php', '/cron/export.php'], array_column($found['rows'], 'label'));
	}

	/** A route may contain the wildcards of LIKE, and a search for them means the characters. */
	public function testTheSearchTakesWildcardsLiterally() {
		$this->bucket('/report/100%', hits: 1, msPerHit: 10);
		$this->bucket('/report/1000', hits: 1, msPerHit: 10);
		$this->bucket('/a_b', hits: 1, msPerHit: 10);
		$this->bucket('/axb', hits: 1, msPerHit: 10);

		$this->assertSame(['/report/100%'], array_column($this->tools->getPerfTopPaths('shop', at: self::AT, pathContains: '100%')['rows'], 'label'));
		$this->assertSame(['/a_b'], array_column($this->tools->getPerfTopPaths('shop', at: self::AT, pathContains: 'a_b')['rows'], 'label'));
	}

	/**
	 * The extras are a query of their own, and it has to be narrowed the same way - or a route would
	 * be handed the database time of the routes the search left out.
	 */
	public function testTheDatabaseOfAFoundRouteIsItsOwn() {
		$this->bucket('/cron/import.php', hits: 2, msPerHit: 1000, extra: [SqlMetrics::QUERIES => 10, SqlMetrics::SECONDS => 0.5]);
		$this->bucket('/checkout', hits: 2, msPerHit: 100, extra: [SqlMetrics::QUERIES => 400, SqlMetrics::SECONDS => 0.1]);

		$found = $this->tools->getPerfTopPaths(at: self::AT, pathContains: 'import');

		$this->assertSame(['shop /cron/import.php'], array_column($found['rows'], 'label'));
		$this->assertEquals(
			['queriesPerHit' => 5.0, 'msPerHit' => 250.0, 'shareOfResponse' => 0.25],
			$found['rows'][0]['database'],
		);
	}

	public function testOneMachineOrVhostCanBePickedOut() {
		$this->bucket('/checkout', hits: 10, msPerHit: 100, server: 'web-01');
		$this->bucket('/checkout', hits: 3, msPerHit: 100, server: 'web-02', host: 'admin.site.com');

		$this->assertSame(3, $this->tools->getPerfTopPaths('shop', at: self::AT, server: 'web-02')['rows'][0]['hits']);
		$this->assertSame(10, $this->tools->getPerfTopPaths('shop', at: self::AT, host: 'www.site.com')['rows'][0]['hits']);
		$this->assertSame(10, $this->tools->getPerfTimeseries('shop', at: self::AT, server: 'web-01')['hits']);
	}

	public function testOneRouteIsReadInFullAndPerMachine() {
		$this->bucket('/checkout', hits: 8, msPerHit: 100, server: 'web-01');
		$this->bucket('/checkout', hits: 2, msPerHit: 3000, server: 'web-02', errors: 2);
		$this->bucket('/login', hits: 50, msPerHit: 10);

		$path = $this->tools->getPerfPath('shop', '/checkout', 1, self::AT);

		$this->assertSame(10, $path['summary']['hits']);
		$this->assertEquals(3000.0, $path['summary']['maxMs']);
		$this->assertSame(0.2, $path['summary']['errorRate']);
		$this->assertSame(['under100Ms' => 0, 'to500Ms' => 8, 'to2s' => 0, 'to10s' => 2, 'over10s' => 0], $path['summary']['latencyBands']);
		$this->assertSame(['web-01', 'web-02'], array_column($path['byServer'], 'label'));
		$this->assertSame(['www.site.com'], array_column($path['byHost'], 'label'));
	}

	/** An exact path that matches nothing is most likely a path typed from memory. */
	public function testAPathWithNoTrafficSaysHowToFindTheRightOne() {
		$this->bucket('/checkout', hits: 1, msPerHit: 100);

		$path = $this->tools->getPerfPath('shop', '/check', 1, self::AT);

		$this->assertNull($path['summary']);
		$this->assertStringContainsString('pathContains', $path['note']);
	}

	public function testTheSeriesListsTheBucketsThatHadTraffic() {
		$this->bucket('/checkout', hits: 4, msPerHit: 100, minute: 1);
		$this->bucket('/checkout', hits: 6, msPerHit: 200, minute: 7);
		$this->bucket('/login', hits: 50, msPerHit: 10, minute: 7);

		$series = $this->tools->getPerfTimeseries('shop', '/checkout', at: self::AT);

		$this->assertSame(10, $series['hits']);
		$this->assertSame(['2026-03-10 10:01:00', '2026-03-10 10:07:00'], array_column($series['points'], 'bucketAt'));
		$this->assertEquals(200.0, $series['points'][1]['avgMs']);
	}

	/** Two hours and more are read from hours, and those exist only once the roll-up has run. */
	public function testAWideWindowWithNothingInItBlamesTheRollupRatherThanTheTraffic() {
		$this->bucket('/checkout', hits: 4, msPerHit: 100);

		$series = $this->tools->getPerfTimeseries('shop', hours: 24, at: '2026-03-10T00:00:00');

		$this->assertSame('hour', $series['window']['granularity']);
		$this->assertSame([], $series['points']);
		$this->assertStringContainsString('app:perf:rollup', $series['note']);
	}

	public function testTheDatabaseIsWhatThePanelSays() {
		$this->bucket('/checkout', hits: 10, msPerHit: 100, extra: [SqlMetrics::QUERIES => 120, SqlMetrics::SECONDS => 0.4]);
		$this->bucket('/login', hits: 10, msPerHit: 100, extra: [SqlMetrics::QUERIES => 40, SqlMetrics::SECONDS => 0.1]);

		$database = $this->tools->getPerfDatabase('shop', at: self::AT);

		$this->assertTrue($database['collected']);
		$this->assertEquals(160.0, $database['queries']);
		$this->assertEquals(8.0, $database['queriesPerHit']);
		$this->assertEquals(25.0, $database['dbMsPerHit']);
		$this->assertSame(0.25, $database['shareOfResponse']);
		$this->assertSame(['/checkout', '/login'], array_column($database['routes'], 'label'));
	}

	public function testTrafficWithoutQueryCountsIsAMissingCollectorBundle() {
		$this->bucket('/checkout', hits: 10, msPerHit: 100);

		$database = $this->tools->getPerfDatabase('shop', at: self::AT);

		$this->assertFalse($database['collected']);
		$this->assertStringContainsString('perf-collector-bundle', $database['note']);
	}

	public function testARegressionComesWithItsRouteAroundTheTimeItWasFound() {
		$this->bucket('/checkout', hits: 3, msPerHit: 3000, minute: 30);
		$regression = $this->regression('/checkout', new DateTimeImmutable('2026-03-10 10:30:00'));
		self::getContainer()->get(EntityManagerInterface::class)->flush();

		$detail = $this->tools->getPerfRegression((string)$regression->getId());

		$this->assertSame('/checkout', $detail['path']);
		$this->assertSame('p95', $detail['metric']);
		$this->assertSame('ms', $detail['unit']);
		$this->assertSame(3100.0, $detail['observed']);
		$this->assertSame('minute', $detail['window']['granularity']);
		$this->assertSame(['2026-03-10 10:30:00'], array_column($detail['points'], 'bucketAt'));
	}

	public function testAnErrorIsNotARegression() {
		$log = RecordLogFactory::createOne(['project' => $this->project])->_real();

		$this->expectException(ToolCallException::class);
		$this->expectExceptionMessage('There is no performance regression with the id');

		$this->tools->getPerfRegression((string)$log->getId());
	}

	/** The page falls back to the live window; an assistant would not notice that it had. */
	public function testAnUnreadableStartIsRefusedRatherThanReplaced() {
		$this->expectException(ToolCallException::class);
		$this->expectExceptionMessage('"yesterday-ish" is not a date I can read as "at"');

		$this->tools->getPerfHealth('shop', at: 'yesterday-ish');
	}

	public function testTheWindowIsClampedTheWayThePageClampsIt() {
		$window = $this->tools->getPerfTimeseries('shop', hours: 100000, at: '2026-01-01T00:00:00')['window'];

		$this->assertSame('2027-01-01 00:00:00', $window['to']);
		$this->assertSame('day', $window['granularity']);
	}

	public function testAnUnknownProjectIsRefused() {
		$this->expectException(ToolCallException::class);
		$this->expectExceptionMessage('Call list_projects for the known ones.');

		$this->tools->getPerfTopPaths('nosuch');
	}

	public function testNothingIsAnsweredWhilePerformanceMonitoringIsOff() {
		$container = self::getContainer();
		$tools     = new PerfTools(
			$container->get(PerfReportBuilder::class),
			$container->get(ToolArguments::class),
			new PerfPresenter(),
			$container->get(ProjectRepository::class),
			$container->get(RecordPerformanceRepository::class),
			false,
		);

		$this->expectException(ToolCallException::class);
		$this->expectExceptionMessage('Performance monitoring is off');

		$tools->getPerfHealth();
	}

	private function regression(string $path, ?DateTimeImmutable $windowAt = null): RecordPerformance {
		$record = new RecordPerformance(
			$this->project, $path, 'p95', PerfUnit::Milliseconds, 210.0, 3100.0,
			$windowAt ?? new DateTimeImmutable('-10 minutes'),
		);
		self::getContainer()->get(EntityManagerInterface::class)->persist($record);

		return $record;
	}

	/** @param array<string, int|float> $extra */
	private function bucket(
		string $path,
		int    $hits,
		int    $msPerHit,
		float  $userMs = 0.0,
		float  $sysMs = 0.0,
		int    $memPerHit = 0,
		int    $errors = 0,
		int    $minute = 0,
		string $server = 'web-01',
		string $host = 'www.site.com',
		array  $extra = [],
	): void {
		$histogram                                          = HistogramBins::empty();
		$histogram[HistogramBins::binFor((float)$msPerHit)] = $hits;

		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				PerfGranularity::Minute,
				new DateTimeImmutable(sprintf('2026-03-10 10:%02d:00', $minute)),
				$this->project,
				$server,
				$host,
				$path,
				hits: $hits,
				sumDuration: $hits * $msPerHit / 1000,
				sumUser: $hits * $userMs / 1000,
				sumSys: $hits * $sysMs / 1000,
				maxDuration: $msPerHit / 1000,
				sumMem: $hits * $memPerHit,
				maxMem: $memPerHit,
				serverErrors: $errors,
				durationHistogram: $histogram,
				extra: $extra,
			),
		]);
	}
}
