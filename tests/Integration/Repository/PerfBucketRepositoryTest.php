<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Repository;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\PerfWindow;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\Factories;

class PerfBucketRepositoryTest extends KernelTestCase
{
	use Factories;

	private function repository(): PerfBucketRepository
	{
		return self::getContainer()->get(PerfBucketRepository::class);
	}

	public function testTheRepositoryIsAServiceOfItsOwn(): void
	{
		$this->assertInstanceOf(PerfBucketRepository::class, $this->repository());
	}

	public function testABucketIsFoundByTheKeyThatIdentifiesIt(): void
	{
		$project = ProjectFactory::createOne()->_real();
		$this->persist($this->bucket($project));

		$found = $this->repository()->findOneByKey(
			PerfGranularity::Minute,
			new DateTimeImmutable('2026-03-10 14:37:00'),
			$project,
			'web-01',
			'www.site.com',
			'/user/{id}',
		);

		$this->assertNotNull($found);
		$this->assertSame('/user/{id}', $found->getPath());
	}

	public function testTheKeyIsLookedUpByTheTimestampsBucketAndNotByTheTimestamp(): void
	{
		$project = ProjectFactory::createOne()->_real();
		$this->persist($this->bucket($project));

		$found = $this->repository()->findOneByKey(
			PerfGranularity::Minute,
			new DateTimeImmutable('2026-03-10 14:37:41.9'),
			$project,
			'web-01',
			'www.site.com',
			'/user/{id}',
		);

		$this->assertNotNull($found);
	}

	/**
	 * @dataProvider differingKeys
	 */
	public function testEveryPartOfTheKeyTellsBucketsApart(array $overrides): void
	{
		$project = ProjectFactory::createOne()->_real();
		$this->persist($this->bucket($project));

		$key = [
			'granularity' => PerfGranularity::Minute,
			'bucketAt'    => new DateTimeImmutable('2026-03-10 14:37:00'),
			'serverName'  => 'web-01',
			'host'        => 'www.site.com',
			'path'        => '/user/{id}',
			...$overrides,
		];

		$this->assertNull($this->repository()->findOneByKey(
			$key['granularity'],
			$key['bucketAt'],
			$project,
			$key['serverName'],
			$key['host'],
			$key['path'],
		));
	}

	/** @return array<string, array{array<string, mixed>}> */
	public static function differingKeys(): array
	{
		return [
			'another granularity' => [['granularity' => PerfGranularity::Hour]],
			'another minute'      => [['bucketAt' => new DateTimeImmutable('2026-03-10 14:38:00')]],
			'another machine'     => [['serverName' => 'web-02']],
			'another vhost'       => [['host' => 'api.site.com']],
			'another path'        => [['path' => '/user/{id}/edit']],
		];
	}

	public function testAnotherProjectsBucketIsNotThisProjectsBucket(): void
	{
		$project = ProjectFactory::createOne()->_real();
		$other   = ProjectFactory::createOne()->_real();
		$this->persist($this->bucket($project));

		$this->assertNull($this->repository()->findOneByKey(
			PerfGranularity::Minute,
			new DateTimeImmutable('2026-03-10 14:37:00'),
			$other,
			'web-01',
			'www.site.com',
			'/user/{id}',
		));
	}

	public function testOnlyTheProjectsThatMeasuredAnythingInTheWindowAreRolledUp(): void
	{
		$measured = ProjectFactory::createOne()->_real();
		$earlier  = ProjectFactory::createOne()->_real();
		$coarser  = ProjectFactory::createOne()->_real();
		ProjectFactory::createOne();

		$this->persist($this->minute($measured, '2026-03-10 14:37:00'));
		$this->persist($this->minute($earlier, '2026-03-10 13:59:00'));
		$this->persist($this->bucket($coarser, PerfGranularity::Hour, '2026-03-10 14:00:00'));

		$projects = $this->repository()->projectsWithBuckets(
			PerfGranularity::Minute,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 15:00:00'),
		);

		$this->assertSame(
			[$measured->getId()->toRfc4122()],
			array_map(static fn(Project $p): string => $p->getId()->toRfc4122(), $projects),
		);
	}

	/**
	 * An hour with no minutes under it is not an hour anybody has to compute. Asking the table
	 * which boundaries exist, rather than walking every boundary in the window, is what keeps a
	 * `--from` three months back from running thousands of aggregations over nothing.
	 */
	public function testOnlyTheBoundariesWithSourceRowsUnderThemAreRolledUp(): void
	{
		$project = ProjectFactory::createOne()->_real();
		$other   = ProjectFactory::createOne()->_real();

		foreach (['2026-03-10 14:03:00', '2026-03-10 14:37:00', '2026-03-10 16:01:00'] as $at) {
			$this->persist($this->minute($project, $at));
		}
		$this->persist($this->minute($project, '2026-03-10 17:30:00'));
		$this->persist($this->minute($other, '2026-03-10 15:05:00'));

		$boundaries = $this->repository()->boundariesToRollUp(
			new PerfWindow(
				new DateTimeImmutable('2026-03-10 14:00:00'),
				new DateTimeImmutable('2026-03-10 17:00:00'),
				PerfGranularity::Hour,
			),
			$project,
		);

		$this->assertSame(
			['2026-03-10 14:00:00', '2026-03-10 16:00:00'],
			array_map(static fn(DateTimeImmutable $at): string => $at->format('Y-m-d H:i:s'), $boundaries),
		);
	}

	/**
	 * The arithmetic of the cascade, done where the rows are. Counts and sums add, maxima take the
	 * greatest, histograms add bin by bin - which is exactly why minute rows can be deleted
	 * afterwards without a long-range chart losing anything.
	 */
	public function testAnHourIsTheSumOfItsMinutes(): void
	{
		$project = ProjectFactory::createOne()->_real();

		$this->persist(new PerfBucket(
			PerfGranularity::Minute,
			new DateTimeImmutable('2026-03-10 14:03:00'),
			$project,
			'web-01',
			'www.site.com',
			'/user/{id}',
			hits: 5,
			sumDuration: 2.5,
			sumUser: 2.0,
			sumSys: 0.25,
			maxDuration: 0.9,
			sumMem: 100,
			maxMem: 3000,
			clientErrors: 1,
			serverErrors: 2,
			durationHistogram: $this->histogram([0 => 2, 8 => 3]),
			extra: ['sq' => 10, 'st' => 0.5],
		));
		$this->persist(new PerfBucket(
			PerfGranularity::Minute,
			new DateTimeImmutable('2026-03-10 14:37:00'),
			$project,
			'web-01',
			'www.site.com',
			'/user/{id}',
			hits: 3,
			sumDuration: 1.5,
			sumUser: 1.0,
			sumSys: 0.25,
			maxDuration: 1.4,
			sumMem: 50,
			maxMem: 1000,
			clientErrors: 2,
			serverErrors: 1,
			durationHistogram: $this->histogram([8 => 1, 15 => 2]),
			extra: ['sq' => 5, 'cache_hits' => 2],
		));

		$rows = $this->repository()->aggregateInto(
			PerfGranularity::Hour,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			$project,
		);

		$this->assertCount(1, $rows);
		$row = $rows[0];

		$this->assertSame(PerfGranularity::Hour, $row->getGranularity());
		$this->assertSame('2026-03-10 14:00:00', $row->getBucketAt()->format('Y-m-d H:i:s'));
		$this->assertSame($project->getId()->toRfc4122(), $row->getProject()->getId()->toRfc4122());
		$this->assertSame('web-01', $row->getServerName());
		$this->assertSame('www.site.com', $row->getHost());
		$this->assertSame('/user/{id}', $row->getPath());
		$this->assertSame(8, $row->getHits());
		$this->assertSame(4.0, $row->getSumDuration());
		$this->assertSame(3.0, $row->getSumUser());
		$this->assertSame(0.5, $row->getSumSys());
		$this->assertSame(1.4, $row->getMaxDuration());
		$this->assertSame(150, $row->getSumMem());
		$this->assertSame(3000, $row->getMaxMem());
		$this->assertSame(3, $row->getClientErrors());
		$this->assertSame(3, $row->getServerErrors());
		$this->assertSame($this->histogram([0 => 2, 8 => 4, 15 => 2]), $row->getDurationHistogram());
		$this->assertSame(['sq' => 15.0, 'st' => 0.5, 'cache_hits' => 2.0], $row->getExtra());
	}

	public function testEachRouteOnEachMachineAndVhostStaysARowOfItsOwn(): void
	{
		$project = ProjectFactory::createOne()->_real();

		$this->persist($this->minute($project, '2026-03-10 14:03:00'));
		$this->persist($this->minute($project, '2026-03-10 14:04:00', path: '/feed/'));
		$this->persist($this->minute($project, '2026-03-10 14:05:00', serverName: 'web-02'));
		$this->persist($this->minute($project, '2026-03-10 14:06:00', host: 'api.site.com'));

		$rows = $this->repository()->aggregateInto(
			PerfGranularity::Hour,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			$project,
		);

		$this->assertCount(4, $rows);
	}

	public function testNothingOutsideTheTargetBucketIsAggregatedIntoIt(): void
	{
		$project = ProjectFactory::createOne()->_real();
		$other   = ProjectFactory::createOne()->_real();

		$this->persist($this->minute($project, '2026-03-10 14:59:00', hits: 4));
		$this->persist($this->minute($project, '2026-03-10 15:00:00', hits: 100));
		$this->persist($this->minute($project, '2026-03-10 13:59:00', hits: 100));
		$this->persist($this->minute($other, '2026-03-10 14:10:00', hits: 100));
		$this->persist($this->bucket($project, PerfGranularity::Hour, '2026-03-10 14:00:00', hits: 100));

		$rows = $this->repository()->aggregateInto(
			PerfGranularity::Hour,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			$project,
		);

		$this->assertCount(1, $rows);
		$this->assertSame(4, $rows[0]->getHits());
	}

	public function testATargetBucketWithNothingUnderItAggregatesToNothing(): void
	{
		$project = ProjectFactory::createOne()->_real();

		$this->assertSame([], $this->repository()->aggregateInto(
			PerfGranularity::Hour,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			$project,
		));
	}

	/** The timestamp names a bucket, so any instant inside it names the same one. */
	public function testTheTargetBucketIsTakenFromTheInstantsBucket(): void
	{
		$project = ProjectFactory::createOne()->_real();
		$this->persist($this->minute($project, '2026-03-10 14:03:00'));

		$rows = $this->repository()->aggregateInto(
			PerfGranularity::Hour,
			new DateTimeImmutable('2026-03-10 14:41:17'),
			$project,
		);

		$this->assertCount(1, $rows);
		$this->assertSame('2026-03-10 14:00:00', $rows[0]->getBucketAt()->format('Y-m-d H:i:s'));
	}

	public function testADayIsTheSumOfItsHoursAndNotOfItsMinutes(): void
	{
		$project = ProjectFactory::createOne()->_real();

		$this->persist($this->bucket($project, PerfGranularity::Hour, '2026-03-10 09:00:00', hits: 7));
		$this->persist($this->bucket($project, PerfGranularity::Hour, '2026-03-10 18:00:00', hits: 2));
		$this->persist($this->minute($project, '2026-03-10 09:03:00', hits: 100));

		$rows = $this->repository()->aggregateInto(
			PerfGranularity::Day,
			new DateTimeImmutable('2026-03-10 00:00:00'),
			$project,
		);

		$this->assertCount(1, $rows);
		$this->assertSame(9, $rows[0]->getHits());
	}

	/**
	 * Detection asks about routes, not about machines: a regression that shows on one server out
	 * of three is still a regression of the route, and the record says so.
	 */
	public function testAWindowIsAggregatedPerRouteAcrossMachinesAndVhosts(): void
	{
		$project = ProjectFactory::createOne()->_real();

		$this->persist($this->minute($project, '2026-03-10 14:03:00', hits: 5));
		$this->persist($this->minute($project, '2026-03-10 14:04:00', hits: 3, serverName: 'web-02'));
		$this->persist($this->minute($project, '2026-03-10 14:05:00', hits: 2, host: 'api.site.com'));
		$this->persist($this->minute($project, '2026-03-10 14:06:00', path: '/feed/', hits: 7));
		$this->persist($this->minute($project, '2026-03-10 15:00:00', hits: 100));

		$stats = $this->repository()->aggregateByPath(
			PerfGranularity::Minute,
			$project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 15:00:00'),
		);

		$this->assertSame(
			[PerfBucket::hashPath('/user/{id}'), PerfBucket::hashPath('/feed/')],
			array_keys($stats),
		);
		$this->assertSame(10, $stats[PerfBucket::hashPath('/user/{id}')]->hits);
		$this->assertSame('/user/{id}', $stats[PerfBucket::hashPath('/user/{id}')]->path);
		$this->assertSame(7, $stats[PerfBucket::hashPath('/feed/')]->hits);
	}

	/** Without this a metric registered under `perf.metrics` has nothing of its own to read. */
	public function testTheApplicationsOwnMetricsReachTheWindowToo(): void
	{
		$project = ProjectFactory::createOne()->_real();
		$em      = self::getContainer()->get(EntityManagerInterface::class);

		(new PerfBucketUpserter($em))->upsert([
			new PerfBucket(
				PerfGranularity::Minute,
				new DateTimeImmutable('2026-03-10 14:03:00'),
				$project,
				'web-01',
				'www.site.com',
				'/user/{id}',
				hits: 2,
				extra: ['sq' => 10, 'st' => 0.5],
			),
			new PerfBucket(
				PerfGranularity::Minute,
				new DateTimeImmutable('2026-03-10 14:04:00'),
				$project,
				'web-02',
				'www.site.com',
				'/user/{id}',
				hits: 1,
				extra: ['sq' => 5],
			),
		]);

		$stats = $this->repository()->aggregateByPath(
			PerfGranularity::Minute,
			$project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 15:00:00'),
		);

		$this->assertSame(['sq' => 15.0, 'st' => 0.5], $stats[PerfBucket::hashPath('/user/{id}')]->extra);
	}

	public function testAWindowWithNoTrafficHasNoRoutes(): void
	{
		$project = ProjectFactory::createOne()->_real();

		$this->assertSame([], $this->repository()->aggregateByPath(
			PerfGranularity::Minute,
			$project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 15:00:00'),
		));
	}

	private function bucket(
		Project $project,
		PerfGranularity $granularity = PerfGranularity::Minute,
		string $bucketAt = '2026-03-10 14:37:00',
		string $path = '/user/{id}',
		string $serverName = 'web-01',
		string $host = 'www.site.com',
		int $hits = 3,
	): PerfBucket {
		return new PerfBucket(
			$granularity,
			new DateTimeImmutable($bucketAt),
			$project,
			$serverName,
			$host,
			$path,
			hits: $hits,
		);
	}

	private function minute(
		Project $project,
		string $bucketAt,
		string $path = '/user/{id}',
		string $serverName = 'web-01',
		string $host = 'www.site.com',
		int $hits = 3,
	): PerfBucket {
		return $this->bucket($project, PerfGranularity::Minute, $bucketAt, $path, $serverName, $host, $hits);
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

	private function persist(PerfBucket $bucket): void
	{
		$em = self::getContainer()->get(EntityManagerInterface::class);
		$em->persist($bucket);
		$em->flush();
	}
}
