<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Perf;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\PerfWindow;
use BugCatcher\Service\Perf\Rollup\BucketMerger;
use BugCatcher\Service\Perf\Rollup\PathCapEnforcer;
use BugCatcher\Service\Perf\Rollup\RollupService;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Log\NullLogger;
use Zenstruck\Foundry\Test\Factories;

class RollupServiceTest extends KernelTestCase
{
	use Factories;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne()->_real();
	}

	/**
	 * The cascade is exact, which is the whole reason minute rows can be deleted after seven days
	 * without a long-range chart losing anything.
	 */
	public function testAnHourIsEveryMinuteUnderIt(): void
	{
		$this->store($this->minute('14:03', hits: 5, sumDuration: 2.5, maxDuration: 0.9, histogram: [8 => 5], extra: ['sq' => 10]));
		$this->store($this->minute('14:37', hits: 3, sumDuration: 1.5, maxDuration: 1.4, histogram: [9 => 3], extra: ['sq' => 5]));
		$this->store($this->minute('15:01', hits: 9));

		$result = $this->service()->rollUp($this->hourWindow());

		$this->assertSame(1, $result->boundaries);
		$this->assertSame(1, $result->rowsWritten);
		$this->assertSame(0, $result->foldedPaths);

		$hour = $this->stored(PerfGranularity::Hour, '2026-03-10 14:00:00');
		$this->assertNotNull($hour);
		$this->assertSame(8, $hour->getHits());
		$this->assertSame(4.0, $hour->getSumDuration());
		$this->assertSame(1.4, $hour->getMaxDuration());
		$this->assertSame($this->histogram([8 => 5, 9 => 3]), $hour->getDurationHistogram());
		$this->assertSame(['sq' => 15.0], $hour->getExtra());

		$this->assertNull($this->stored(PerfGranularity::Hour, '2026-03-10 15:00:00'));
	}

	/**
	 * A roll-up is recomputed, not accumulated, so running it again over a window it has already
	 * done changes nothing. Cron re-runs, a manual `--from` over last week and a retry after a
	 * failure all depend on it.
	 */
	public function testRunningItTwiceOverTheSameWindowChangesNothing(): void
	{
		$this->store($this->minute('14:03', hits: 5, extra: ['sq' => 10]));

		$this->service()->rollUp($this->hourWindow());
		$this->service()->rollUp($this->hourWindow());

		$hour = $this->stored(PerfGranularity::Hour, '2026-03-10 14:00:00');
		$this->assertSame(5, $hour->getHits());
		$this->assertSame(['sq' => 10.0], $hour->getExtra());
	}

	/**
	 * The hook writes its line in shutdown, so a minute can still grow after the hour above it was
	 * computed. The next roll-up has to see the whole hour again - which is exactly what makes
	 * recomputing the right semantics and adding the wrong one.
	 */
	public function testAMinuteThatArrivesLateIsPickedUpByTheNextRun(): void
	{
		$this->store($this->minute('14:03', hits: 5));
		$this->service()->rollUp($this->hourWindow());

		$this->store($this->minute('14:58', hits: 2));
		$this->service()->rollUp($this->hourWindow());

		$this->assertSame(7, $this->stored(PerfGranularity::Hour, '2026-03-10 14:00:00')->getHits());
	}

	public function testADayIsBuiltFromHoursAndNotFromMinutes(): void
	{
		$this->store($this->bucket(PerfGranularity::Hour, '2026-03-10 09:00:00', hits: 7));
		$this->store($this->bucket(PerfGranularity::Hour, '2026-03-10 18:00:00', hits: 2));
		$this->store($this->minute('09:03', hits: 100));

		$this->service()->rollUp(new PerfWindow(
			new DateTimeImmutable('2026-03-10 00:00:00'),
			new DateTimeImmutable('2026-03-11 00:00:00'),
			PerfGranularity::Day,
		));

		$this->assertSame(9, $this->stored(PerfGranularity::Day, '2026-03-10 00:00:00')->getHits());
	}

	public function testEveryHourInTheWindowIsComputed(): void
	{
		$this->store($this->minute('14:03', hits: 1));
		$this->store($this->minute('15:03', hits: 2));
		$this->store($this->minute('16:03', hits: 4));

		$result = $this->service()->rollUp(new PerfWindow(
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 17:00:00'),
			PerfGranularity::Hour,
		));

		$this->assertSame(3, $result->boundaries);
		$this->assertSame(3, $result->rowsWritten);
		$this->assertSame(1, $this->stored(PerfGranularity::Hour, '2026-03-10 14:00:00')->getHits());
		$this->assertSame(2, $this->stored(PerfGranularity::Hour, '2026-03-10 15:00:00')->getHits());
		$this->assertSame(4, $this->stored(PerfGranularity::Hour, '2026-03-10 16:00:00')->getHits());
	}

	public function testEveryProjectThatMeasuredSomethingIsRolledUp(): void
	{
		$other = ProjectFactory::createOne()->_real();

		$this->store($this->minute('14:03', hits: 1));
		$this->store($this->minute('14:04', hits: 6, project: $other));

		$this->service()->rollUp($this->hourWindow());

		$this->assertSame(1, $this->stored(PerfGranularity::Hour, '2026-03-10 14:00:00')->getHits());
		$this->assertSame(6, $this->stored(PerfGranularity::Hour, '2026-03-10 14:00:00', project: $other)->getHits());
	}

	public function testTheTailBeyondThePathCapIsFoldedAndCounted(): void
	{
		$this->store($this->minute('14:03', path: '/busy', hits: 50));
		$this->store($this->minute('14:03', path: '/next', hits: 20));
		$this->store($this->minute('14:03', path: '/user/1', hits: 3));
		$this->store($this->minute('14:04', path: '/user/2', hits: 2));

		$result = $this->service(cap: 2)->rollUp($this->hourWindow());

		$this->assertSame(2, $result->foldedPaths);
		$this->assertSame(3, $result->rowsWritten);
		$this->assertSame(50, $this->stored(PerfGranularity::Hour, '2026-03-10 14:00:00', '/busy')->getHits());
		$this->assertSame(20, $this->stored(PerfGranularity::Hour, '2026-03-10 14:00:00', '/next')->getHits());
		$this->assertSame(
			5,
			$this->stored(PerfGranularity::Hour, '2026-03-10 14:00:00', PerfBucket::OTHER_PATH)->getHits(),
		);
		$this->assertNull($this->stored(PerfGranularity::Hour, '2026-03-10 14:00:00', '/user/1'));
	}

	public function testAWindowWithNothingInItWritesNothing(): void
	{
		$result = $this->service()->rollUp($this->hourWindow());

		$this->assertSame(0, $result->boundaries);
		$this->assertSame(0, $result->rowsWritten);
	}

	/** Minutes are what the collector ships; nothing rolls up into them. */
	public function testThereIsNoRollUpIntoMinutes(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->service()->rollUp(new PerfWindow(
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 15:00:00'),
			PerfGranularity::Minute,
		));
	}

	private function service(int $cap = 1000): RollupService
	{
		$em = self::getContainer()->get(EntityManagerInterface::class);

		return new RollupService(
			self::getContainer()->get(PerfBucketRepository::class),
			new PerfBucketUpserter($em),
			new PathCapEnforcer(new BucketMerger(), $cap),
			new NullLogger(),
		);
	}

	private function hourWindow(): PerfWindow
	{
		return new PerfWindow(
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 15:00:00'),
			PerfGranularity::Hour,
		);
	}

	private function minute(
		string $time,
		string $path = '/user/{id}',
		int $hits = 1,
		float $sumDuration = 0.0,
		float $maxDuration = 0.0,
		array $histogram = [],
		array $extra = [],
		?Project $project = null,
	): PerfBucket {
		return $this->bucket(
			PerfGranularity::Minute,
			"2026-03-10 {$time}:00",
			$path,
			$hits,
			$sumDuration,
			$maxDuration,
			$histogram,
			$extra,
			$project,
		);
	}

	private function bucket(
		PerfGranularity $granularity,
		string $bucketAt,
		string $path = '/user/{id}',
		int $hits = 1,
		float $sumDuration = 0.0,
		float $maxDuration = 0.0,
		array $histogram = [],
		array $extra = [],
		?Project $project = null,
	): PerfBucket {
		return new PerfBucket(
			$granularity,
			new DateTimeImmutable($bucketAt),
			$project ?? $this->project,
			'web-01',
			'www.site.com',
			$path,
			hits: $hits,
			sumDuration: $sumDuration,
			maxDuration: $maxDuration,
			durationHistogram: $this->histogram($histogram),
			extra: $extra,
		);
	}

	private function store(PerfBucket $bucket): void
	{
		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([$bucket]);
	}

	private function stored(
		PerfGranularity $granularity,
		string $bucketAt,
		string $path = '/user/{id}',
		?Project $project = null,
	): ?PerfBucket {
		$em = self::getContainer()->get(EntityManagerInterface::class);
		$em->clear();

		return self::getContainer()->get(PerfBucketRepository::class)->findOneByKey(
			$granularity,
			new DateTimeImmutable($bucketAt),
			$project ?? $this->project,
			'web-01',
			'www.site.com',
			$path,
		);
	}

	/**
	 * @param array<int, int> $counts
	 * @return list<int>
	 */
	private function histogram(array $counts): array
	{
		$histogram = HistogramBins::empty();
		foreach ($counts as $bin => $count) {
			$histogram[$bin] = $count;
		}

		return $histogram;
	}
}
