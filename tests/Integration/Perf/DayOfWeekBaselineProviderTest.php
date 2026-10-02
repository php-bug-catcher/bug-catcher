<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Perf;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\Detection\DayOfWeekBaselineProvider;
use BugCatcher\Service\Perf\Detection\Extractor\AvgMetricExtractor;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\PerfWindow;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\Factories;

/**
 * The baseline is the same clock time on previous same weekdays. Monday morning is not Sunday
 * night, and a baseline that ignores that alerts every week.
 */
class DayOfWeekBaselineProviderTest extends KernelTestCase
{
	use Factories;

	/** A Tuesday. The window is five minutes inside the 14:00 hour. */
	private const string WINDOW_FROM = '2026-03-10 14:35:00';

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne()->_real();
	}

	public function testTheBaselineIsTheMiddleOfTheSameHourOnPreviousSameWeekdays(): void
	{
		$this->hour('2026-03-03 14:00:00', hits: 10, sumDuration: 1.0);
		$this->hour('2026-02-24 14:00:00', hits: 10, sumDuration: 3.0);
		$this->hour('2026-02-17 14:00:00', hits: 10, sumDuration: 2.0);

		// 100 ms, 300 ms and 200 ms, so the middle one
		$this->assertSame(200.0, $this->baseline(lookbackWeeks: 3));
	}

	/** An even number of samples has no middle, so the two nearest it share the answer. */
	public function testAnEvenNumberOfWeeksAveragesTheTwoInTheMiddle(): void
	{
		$this->hour('2026-03-03 14:00:00', hits: 10, sumDuration: 1.0);
		$this->hour('2026-02-24 14:00:00', hits: 10, sumDuration: 2.0);

		$this->assertSame(150.0, $this->baseline(lookbackWeeks: 2));
	}

	/**
	 * The median rather than the mean: one bad week - a deployment, an import, an outage - should
	 * not be able to raise the bar for a month.
	 */
	public function testOneBadWeekDoesNotMoveTheBaseline(): void
	{
		$this->hour('2026-03-03 14:00:00', hits: 10, sumDuration: 1.0);
		$this->hour('2026-02-24 14:00:00', hits: 10, sumDuration: 1.0);
		$this->hour('2026-02-17 14:00:00', hits: 10, sumDuration: 100.0);

		$this->assertSame(100.0, $this->baseline(lookbackWeeks: 3));
	}

	public function testAWeekWithNoTrafficIsSkippedRatherThanCountedAsZero(): void
	{
		$this->hour('2026-03-03 14:00:00', hits: 10, sumDuration: 1.0);
		// nothing a fortnight ago
		$this->hour('2026-02-17 14:00:00', hits: 10, sumDuration: 3.0);

		$this->assertSame(200.0, $this->baseline(lookbackWeeks: 3));
	}

	/**
	 * Which is also what a fresh installation looks like: nothing is said about a route until
	 * there is a week of history behind it.
	 */
	public function testWithoutAnyHistoryThereIsNoBaseline(): void
	{
		$this->assertNull($this->baseline(lookbackWeeks: 4));
	}

	/**
	 * Minute rows are kept for a week by default, so a baseline that read them would stop
	 * existing the moment the retention caught up with it. Hours are kept for ninety days.
	 */
	public function testTheBaselineReadsHoursAndNotMinutes(): void
	{
		$this->bucket(PerfGranularity::Minute, '2026-03-03 14:35:00', hits: 10, sumDuration: 1.0);

		$this->assertNull($this->baseline(lookbackWeeks: 2));
	}

	public function testAnotherRouteIsAnotherBaseline(): void
	{
		$this->hour('2026-03-03 14:00:00', hits: 10, sumDuration: 1.0);
		$this->hour('2026-03-03 14:00:00', hits: 10, sumDuration: 9.0, path: '/feed/');

		$this->assertSame(100.0, $this->baseline(lookbackWeeks: 1));
		$this->assertSame(900.0, $this->baseline(lookbackWeeks: 1, path: '/feed/'));
	}

	public function testAnotherProjectIsAnotherBaseline(): void
	{
		$other = ProjectFactory::createOne()->_real();
		$this->hour('2026-03-03 14:00:00', hits: 10, sumDuration: 1.0, project: $other);

		$this->assertNull($this->baseline(lookbackWeeks: 1));
	}

	private function baseline(int $lookbackWeeks, string $path = '/user/{id}'): ?float
	{
		$provider = new DayOfWeekBaselineProvider(
			self::getContainer()->get(PerfBucketRepository::class),
			$lookbackWeeks,
		);

		return $provider->baselineFor(
			$this->project,
			PerfBucket::hashPath($path),
			new PerfWindow(
				new DateTimeImmutable(self::WINDOW_FROM),
				new DateTimeImmutable('2026-03-10 14:40:00'),
				PerfGranularity::Minute,
			),
			new AvgMetricExtractor(),
		);
	}

	private function hour(
		string $bucketAt,
		int $hits,
		float $sumDuration,
		string $path = '/user/{id}',
		?Project $project = null,
	): void {
		$this->bucket(PerfGranularity::Hour, $bucketAt, $hits, $sumDuration, $path, $project);
	}

	private function bucket(
		PerfGranularity $granularity,
		string $bucketAt,
		int $hits,
		float $sumDuration,
		string $path = '/user/{id}',
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
				sumDuration: $sumDuration,
			),
		]);
	}
}
