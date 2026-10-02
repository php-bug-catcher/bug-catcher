<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Perf;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\Detection\AnomalyFinding;
use BugCatcher\Service\Perf\Detection\ConjunctiveThresholdPolicy;
use BugCatcher\Service\Perf\Detection\DayOfWeekBaselineProvider;
use BugCatcher\Service\Perf\Detection\Extractor\AvgMetricExtractor;
use BugCatcher\Service\Perf\Detection\MetricExtractorRegistry;
use BugCatcher\Service\Perf\Detection\RegressionDetector;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\PerfWindow;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\Factories;

class RegressionDetectorTest extends KernelTestCase
{
	use Factories;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne()->_real();
	}

	public function testARouteThatGotMuchSlowerIsReported(): void
	{
		$this->history('/checkout', msPerHit: 100);
		$this->observed('/checkout', msPerHit: 3100);

		$findings = $this->detect();

		$this->assertCount(1, $findings);
		$finding = $findings[0];
		$this->assertInstanceOf(AnomalyFinding::class, $finding);
		$this->assertSame($this->project, $finding->project);
		$this->assertSame('/checkout', $finding->path);
		$this->assertSame('avg', $finding->metric);
		$this->assertSame(PerfUnit::Milliseconds, $finding->unit);
		$this->assertSame(100.0, $finding->baseline);
		$this->assertSame(3100.0, $finding->observed);
		$this->assertSame('2026-03-10 14:35:00', $finding->windowAt->format('Y-m-d H:i:s'));
	}

	public function testARouteThatKeptItsSpeedIsNotReported(): void
	{
		$this->history('/checkout', msPerHit: 100);
		$this->observed('/checkout', msPerHit: 110);

		$this->assertSame([], $this->detect());
	}

	/** A route nobody has a week of history for is not a route anybody can judge. */
	public function testARouteWithoutHistoryIsNotReported(): void
	{
		$this->observed('/checkout', msPerHit: 3100);

		$this->assertSame([], $this->detect());
	}

	public function testOnlyTheRoutesThatRegressedAreReported(): void
	{
		$this->history('/checkout', msPerHit: 100);
		$this->history('/feed/', msPerHit: 100);
		$this->observed('/checkout', msPerHit: 3100);
		$this->observed('/feed/', msPerHit: 105);

		$findings = $this->detect();

		$this->assertCount(1, $findings);
		$this->assertSame('/checkout', $findings[0]->path);
	}

	/**
	 * `__other__` is the roll-up's overflow row, not a route. "Everything else got slower" is not
	 * something anybody can open and fix, and the fix for seeing it is a normalisation rule.
	 */
	public function testTheOverflowRowIsNeverReportedAsARoute(): void
	{
		$this->history(PerfBucket::OTHER_PATH, msPerHit: 100);
		$this->observed(PerfBucket::OTHER_PATH, msPerHit: 3100);

		$this->assertSame([], $this->detect());
	}

	public function testAnotherProjectsTrafficIsNotThisProjectsRegression(): void
	{
		$other = ProjectFactory::createOne()->_real();
		$this->history('/checkout', msPerHit: 100);
		$this->observed('/checkout', msPerHit: 3100, project: $other);

		$this->assertSame([], $this->detect());
	}

	public function testTheDetectorIsNamedSoThatConfigurationCanSelectIt(): void
	{
		$this->assertSame('regression', $this->detector()->name());
	}

	/** @return list<AnomalyFinding> */
	private function detect(): array
	{
		return iterator_to_array($this->detector()->detect($this->project, $this->window()), false);
	}

	private function detector(): RegressionDetector
	{
		$repository = self::getContainer()->get(PerfBucketRepository::class);

		return new RegressionDetector(
			$repository,
			new MetricExtractorRegistry(['avg' => new AvgMetricExtractor()]),
			new DayOfWeekBaselineProvider($repository, 2),
			new ConjunctiveThresholdPolicy(factor: 3.0, minAbsoluteMs: 200, minHits: 20),
			'avg',
		);
	}

	private function window(): PerfWindow
	{
		return new PerfWindow(
			new DateTimeImmutable('2026-03-10 14:35:00'),
			new DateTimeImmutable('2026-03-10 14:40:00'),
			PerfGranularity::Minute,
		);
	}

	/** Two same weekdays of hourly history, both at the same speed. */
	private function history(string $path, int $msPerHit): void
	{
		foreach (['2026-03-03 14:00:00', '2026-02-24 14:00:00'] as $at) {
			$this->store(PerfGranularity::Hour, $at, $path, 40, $msPerHit);
		}
	}

	private function observed(string $path, int $msPerHit, ?Project $project = null): void
	{
		$this->store(PerfGranularity::Minute, '2026-03-10 14:36:00', $path, 40, $msPerHit, $project);
	}

	private function store(
		PerfGranularity $granularity,
		string $bucketAt,
		string $path,
		int $hits,
		int $msPerHit,
		?Project $project = null,
	): void {
		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				$granularity,
				new DateTimeImmutable($bucketAt),
				$project ?? $this->project,
				'web-01',
				'www.site.com',
				$path,
				hits: $hits,
				sumDuration: $hits * $msPerHit / 1000,
			),
		]);
	}
}
