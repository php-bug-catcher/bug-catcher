<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Twig;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\SqlMetrics;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Twig\Components\PerfDatabase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;

class PerfDatabaseTest extends KernelTestCase
{
	use Factories;
	use InteractsWithTwigComponents;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne()->_real();
	}

	public function testItCountsTheQueriesOfTheWindowPerRequest(): void
	{
		$this->measured('/checkout', hits: 10, queries: 120, dbSeconds: 0.4);
		$this->measured('/login', hits: 10, queries: 40, dbSeconds: 0.1);

		$component = $this->component();

		$this->assertSame(160.0, $component->getQueries());
		$this->assertSame(8.0, $component->getQueriesPerHit());
		$this->assertEqualsWithDelta(25.0, $component->getDbMsPerHit(), 0.001);
	}

	/**
	 * The number the panel leads with: a query count means nothing until you know whether it
	 * cost 2 % of the response or most of it.
	 */
	public function testItSaysWhatShareOfTheResponseWasTheDatabase(): void
	{
		// 10 requests of 100 ms, half a second of it in the database
		$this->measured('/checkout', hits: 10, queries: 50, dbSeconds: 0.5, msPerHit: 100);

		$this->assertEqualsWithDelta(0.5, $this->component()->getShareOfWallclock(), 0.001);
	}

	/** Wallclock and query time are measured by different clocks, so the share is capped. */
	public function testAShareThatOverranTheResponseIsStillAShare(): void
	{
		$this->measured('/checkout', hits: 10, queries: 50, dbSeconds: 5.0, msPerHit: 100);

		$this->assertSame(1.0, $this->component()->getShareOfWallclock());
	}

	public function testTheRoutesAreTheHeaviestOnTheDatabaseFirst(): void
	{
		$this->measured('/login', hits: 10, queries: 20, dbSeconds: 0.1);
		$this->measured('/checkout', hits: 10, queries: 400, dbSeconds: 2.0);
		$this->measured('/about', hits: 10, queries: 2, dbSeconds: 0.01);

		$routes = $this->component()->getRoutes();

		$this->assertSame(['/checkout', '/login', '/about'], array_column($routes, 'label'));
		$this->assertSame(40.0, $this->component()->queriesPerHitOf($routes[0]));
	}

	/**
	 * Traffic with no query count is the collector bundle missing, which is a different thing
	 * from a quiet window - and reads identically unless the panel says so.
	 */
	public function testTrafficWithoutQueryCountsIsSaidOutLoud(): void
	{
		$this->measured('/checkout', hits: 10, queries: null, dbSeconds: null);

		$component = $this->component();

		$this->assertFalse($component->isCollected());
		$this->assertFalse($component->getSeries()->isEmpty());
		$this->assertSame(['queries' => '', 'time' => ''], $component->getCharts());

		$html = (string)$this->renderTwigComponent('PerfDatabase', ['project' => $this->project]);
		$this->assertStringContainsString('traffic but no query counts', $html);
		$this->assertStringNotContainsString('class="atelier-chart"', $html);
	}

	public function testAnEmptyWindowSaysThereIsNothingRatherThanBlamingTheCollector(): void
	{
		$html = (string)$this->renderTwigComponent('PerfDatabase', ['project' => $this->project]);

		$this->assertStringContainsString('No performance data', $html);
		$this->assertStringNotContainsString('traffic but no query counts', $html);
	}

	public function testThePanelDrawsBothChartsAndTheRouteTable(): void
	{
		$this->measured('/checkout', hits: 10, queries: 120, dbSeconds: 0.4);

		$html = (string)$this->renderTwigComponent('PerfDatabase', ['project' => $this->project]);

		$this->assertSame(2, substr_count($html, 'class="atelier-chart"'));
		$this->assertStringContainsString('var(--bc-perf-queries)', $html);
		$this->assertStringContainsString('/checkout', $html);
	}

	/** The dashboard shows every project until somebody picks one, and so does this. */
	public function testWithoutAProjectItCountsThemAll(): void
	{
		$other = ProjectFactory::createOne()->_real();

		$this->measured('/checkout', hits: 10, queries: 100, dbSeconds: 0.4);
		$this->measured('/checkout', hits: 10, queries: 50, dbSeconds: 0.2, project: $other);

		$component = $this->mountTwigComponent('PerfDatabase', []);
		$this->assertInstanceOf(PerfDatabase::class, $component);

		$this->assertSame(150.0, $component->getQueries());

		// and each application's row stands on its own numbers
		$this->assertSame(
			[100.0, 50.0],
			array_map(
				static fn($row): ?float => $row->extraTotal(SqlMetrics::QUERIES),
				$component->getRoutes(),
			),
		);
	}

	/** @param array<string, mixed> $props */
	private function component(array $props = []): PerfDatabase
	{
		$component = $this->mountTwigComponent('PerfDatabase', ['project' => $this->project] + $props);
		$this->assertInstanceOf(PerfDatabase::class, $component);

		return $component;
	}

	private function measured(
		string $path,
		int $hits,
		?int $queries,
		?float $dbSeconds,
		int $msPerHit = 300,
		?Project $project = null,
	): void {
		$histogram                                     = HistogramBins::empty();
		$histogram[HistogramBins::binFor((float)$msPerHit)] = $hits;

		$extra = [];
		if ($queries !== null) {
			$extra[SqlMetrics::QUERIES] = $queries;
		}
		if ($dbSeconds !== null) {
			$extra[SqlMetrics::SECONDS] = $dbSeconds;
		}

		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				PerfGranularity::Minute,
				new DateTimeImmutable('-10 minutes'),
				$project ?? $this->project,
				'web-01',
				'www.site.com',
				$path,
				hits: $hits,
				sumDuration: $hits * $msPerHit / 1000,
				sumUser: 0.12 * $hits,
				sumSys: 0.03 * $hits,
				maxDuration: $msPerHit / 1000,
				sumMem: 1_048_576 * $hits,
				maxMem: 2_097_152,
				durationHistogram: $histogram,
				extra: $extra,
			),
		]);
	}
}
