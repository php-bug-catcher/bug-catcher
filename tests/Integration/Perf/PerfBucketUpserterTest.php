<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Perf;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\Ingest\UpsertMode;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\Factories;

class PerfBucketUpserterTest extends KernelTestCase
{
	use Factories;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne()->_real();
	}

	/**
	 * Built by hand rather than pulled out of the container: the upserter has one dependency, and
	 * a service with one consumer is inlined away by the compiler anyway. That it is autowired is
	 * covered where it matters, by the functional test of the ingest endpoint.
	 */
	private function upserter(): PerfBucketUpserter
	{
		return new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class));
	}

	private function stored(
		PerfGranularity $granularity = PerfGranularity::Minute,
		string $path = '/user/{id}',
		string $host = 'www.site.com',
		string $serverName = 'web-01',
	): ?PerfBucket {
		$em = self::getContainer()->get(EntityManagerInterface::class);
		$em->clear();

		return self::getContainer()->get(PerfBucketRepository::class)->findOneByKey(
			$granularity,
			new DateTimeImmutable('2026-03-10 14:37:00'),
			$this->project,
			$serverName,
			$host,
			$path,
		);
	}

	public function testABucketThatIsNotThereYetIsInserted(): void
	{
		$written = $this->upserter()->upsert([$this->bucket(hits: 5, sumDuration: 2.19)]);

		$this->assertSame(1, $written);

		$stored = $this->stored();
		$this->assertNotNull($stored);
		$this->assertSame(5, $stored->getHits());
		$this->assertSame(2.19, $stored->getSumDuration());
		$this->assertSame('/user/{id}', $stored->getPath());
		$this->assertSame(PerfBucket::hashPath('/user/{id}'), $stored->getPathHash());
		$this->assertSame('web-01', $stored->getServerName());
		$this->assertSame('www.site.com', $stored->getHost());
		$this->assertSame('2026-03-10 14:37:00', $stored->getBucketAt()->format('Y-m-d H:i:s'));
	}

	/**
	 * The hook writes its line in shutdown, so a request that started inside a minute can be
	 * logged well after that minute was shipped. The counters add, which is what lets the late
	 * line land in the bucket it belongs to instead of being dropped or opening a second row.
	 */
	public function testASecondBatchForTheSameBucketAddsToIt(): void
	{
		$this->upserter()->upsert([
			$this->bucket(hits: 5, sumDuration: 2.5, sumUser: 2.0, sumSys: 0.25, sumMem: 100, clientErrors: 1, serverErrors: 2),
		]);
		$this->upserter()->upsert([
			$this->bucket(hits: 3, sumDuration: 1.5, sumUser: 1.0, sumSys: 0.25, sumMem: 50, clientErrors: 2, serverErrors: 1),
		]);

		$stored = $this->stored();
		$this->assertSame(8, $stored->getHits());
		$this->assertSame(4.0, $stored->getSumDuration());
		$this->assertSame(3.0, $stored->getSumUser());
		$this->assertSame(0.5, $stored->getSumSys());
		$this->assertSame(150, $stored->getSumMem());
		$this->assertSame(3, $stored->getClientErrors());
		$this->assertSame(3, $stored->getServerErrors());
	}

	public function testAMaximumTakesTheGreaterOfTheTwoAndNeverTheLatest(): void
	{
		$this->upserter()->upsert([$this->bucket(maxDuration: 0.9, maxMem: 3000)]);
		$this->upserter()->upsert([$this->bucket(maxDuration: 0.2, maxMem: 1000)]);

		$stored = $this->stored();
		$this->assertSame(0.9, $stored->getMaxDuration());
		$this->assertSame(3000, $stored->getMaxMem());

		$this->upserter()->upsert([$this->bucket(maxDuration: 1.4, maxMem: 9000)]);

		$stored = $this->stored();
		$this->assertSame(1.4, $stored->getMaxDuration());
		$this->assertSame(9000, $stored->getMaxMem());
	}

	public function testHistogramsAddBinByBin(): void
	{
		$this->upserter()->upsert([$this->bucket(histogram: [0 => 2, 8 => 4])]);
		$this->upserter()->upsert([$this->bucket(histogram: [8 => 1, 15 => 3])]);

		$this->assertSame(
			$this->histogram([0 => 2, 8 => 5, 15 => 3]),
			$this->stored()->getDurationHistogram(),
		);
	}

	/**
	 * Sixteen BIGINT columns, so adding bins is exact integer arithmetic and what reads back out is
	 * still counts. A JSON column would have come back as floats, MySQL doing the arithmetic in
	 * DOUBLE.
	 */
	public function testAMergedHistogramIsStillCountsAndNotFloats(): void
	{
		$this->upserter()->upsert([$this->bucket(histogram: [3 => 7])]);
		$this->upserter()->upsert([$this->bucket(histogram: [3 => 1])]);

		foreach ($this->stored()->getDurationHistogram() as $bin => $count) {
			$this->assertIsInt($count, "bin {$bin}");
		}
	}

	public function testExtraMetricsAreAddedKeyByKey(): void
	{
		$this->upserter()->upsert([$this->bucket(extra: ['sq' => 10, 'st' => 0.5])]);
		$this->upserter()->upsert([$this->bucket(extra: ['sq' => 5, 'cache_hits' => 2])]);

		$this->assertEquals(
			['sq' => 15, 'st' => 0.5, 'cache_hits' => 2],
			$this->stored()->getExtra(),
		);
	}

	/**
	 * An extra metric is a number, not a count: one application reports a query count under a name
	 * and another reports the time those queries took. The column is a float and so is what reads
	 * back out of it.
	 */
	public function testAnExtraMetricIsAFloat(): void
	{
		$this->upserter()->upsert([$this->bucket(extra: ['sq' => 10])]);
		$this->upserter()->upsert([$this->bucket(extra: ['sq' => 5])]);

		$this->assertSame(['sq' => 15.0], $this->stored()->getExtra());
	}

	/**
	 * The extra rows hang off the bucket's id, which an upsert does not report. Getting this wrong
	 * attaches the second batch's metrics to whatever row happened to be inserted last.
	 */
	public function testExtraMetricsFindTheirBucketOnTheDuplicatePathToo(): void
	{
		$this->upserter()->upsert([$this->bucket(path: '/first', extra: ['sq' => 1])]);
		$this->upserter()->upsert([$this->bucket(path: '/second', extra: ['sq' => 2])]);
		$this->upserter()->upsert([$this->bucket(path: '/first', extra: ['sq' => 10])]);

		$this->assertSame(['sq' => 11.0], $this->stored(path: '/first')->getExtra());
		$this->assertSame(['sq' => 2.0], $this->stored(path: '/second')->getExtra());
	}

	public function testABatchWithoutExtraLeavesTheStoredExtraAlone(): void
	{
		$this->upserter()->upsert([$this->bucket(extra: ['sq' => 10])]);
		$this->upserter()->upsert([$this->bucket(hits: 1)]);

		$this->assertEquals(['sq' => 10.0], $this->stored()->getExtra());
	}

	public function testAnExtraKeyThatCouldReachIntoTheSqlIsRefused(): void
	{
		$this->expectException(\InvalidArgumentException::class);

		$this->upserter()->upsert([$this->bucket(extra: ['a"."b' => 1])]);
	}

	public function testRowsThatDifferInAnyPartOfTheKeyAreRowsOfTheirOwn(): void
	{
		$this->upserter()->upsert([
			$this->bucket(hits: 1),
			$this->bucket(hits: 2, host: 'api.site.com'),
			$this->bucket(hits: 3, serverName: 'web-02'),
			$this->bucket(hits: 4, path: '/user/{id}/edit'),
			$this->bucket(hits: 5, granularity: PerfGranularity::Hour),
		]);

		$this->assertSame(1, $this->stored()->getHits());
		$this->assertSame(2, $this->stored(host: 'api.site.com')->getHits());
		$this->assertSame(3, $this->stored(serverName: 'web-02')->getHits());
		$this->assertSame(4, $this->stored(path: '/user/{id}/edit')->getHits());
		$this->assertSame(5, $this->stored(PerfGranularity::Hour)->getHits());
	}

	public function testAWholeBatchGoesInAtOnce(): void
	{
		$written = $this->upserter()->upsert([
			$this->bucket(hits: 1),
			$this->bucket(hits: 2, path: '/a'),
			$this->bucket(hits: 3, path: '/b'),
		]);

		$this->assertSame(3, $written);
	}

	public function testAnEmptyBatchIsNotAQuery(): void
	{
		$this->assertSame(0, $this->upserter()->upsert([]));
	}

	/**
	 * The roll-up recomputes a whole hour out of its minutes every time it runs, so what it writes
	 * is the answer and not a contribution to one. Adding here would mean a second run over the
	 * same window doubles the hour - and re-running a roll-up is the normal way to pick up minutes
	 * that arrived late.
	 */
	public function testARecomputedBucketReplacesWhatWasThereRatherThanAddingToIt(): void
	{
		$bucket = fn(): PerfBucket => $this->bucket(
			granularity: PerfGranularity::Hour,
			hits: 8,
			sumDuration: 4.0,
			sumUser: 3.0,
			sumSys: 0.5,
			sumMem: 150,
			clientErrors: 3,
			serverErrors: 1,
			histogram: [8 => 8],
			extra: ['sq' => 15],
		);

		$this->upserter()->upsert([$bucket()], UpsertMode::Replace);
		$this->upserter()->upsert([$bucket()], UpsertMode::Replace);

		$stored = $this->stored(PerfGranularity::Hour);
		$this->assertSame(8, $stored->getHits());
		$this->assertSame(4.0, $stored->getSumDuration());
		$this->assertSame(3.0, $stored->getSumUser());
		$this->assertSame(0.5, $stored->getSumSys());
		$this->assertSame(150, $stored->getSumMem());
		$this->assertSame(3, $stored->getClientErrors());
		$this->assertSame(1, $stored->getServerErrors());
		$this->assertSame($this->histogram([8 => 8]), $stored->getDurationHistogram());
		$this->assertSame(['sq' => 15.0], $stored->getExtra());
	}

	/**
	 * A recomputed maximum is authoritative, including downwards: the only way the peak of an hour
	 * falls is that the minute holding it was never really there, and `GREATEST` would keep a
	 * number nothing measured for as long as the row lives.
	 */
	public function testARecomputedMaximumMayAlsoBeLowerThanTheStoredOne(): void
	{
		$this->upserter()->upsert([$this->bucket(maxDuration: 9.0, maxMem: 9000)], UpsertMode::Replace);
		$this->upserter()->upsert([$this->bucket(maxDuration: 1.5, maxMem: 1500)], UpsertMode::Replace);

		$stored = $this->stored();
		$this->assertSame(1.5, $stored->getMaxDuration());
		$this->assertSame(1500, $stored->getMaxMem());
	}

	/** Ingest keeps adding whatever the roll-up does to the hour rows next to it. */
	public function testTheModeBelongsToTheCallerAndNotToTheRow(): void
	{
		$this->upserter()->upsert([$this->bucket(hits: 5)], UpsertMode::Replace);
		$this->upserter()->upsert([$this->bucket(hits: 3)]);

		$this->assertSame(8, $this->stored()->getHits());
	}

	private function bucket(
		PerfGranularity $granularity = PerfGranularity::Minute,
		string $path = '/user/{id}',
		string $host = 'www.site.com',
		string $serverName = 'web-01',
		int $hits = 1,
		float $sumDuration = 0.0,
		float $sumUser = 0.0,
		float $sumSys = 0.0,
		float $maxDuration = 0.0,
		int $sumMem = 0,
		int $maxMem = 0,
		int $clientErrors = 0,
		int $serverErrors = 0,
		array $histogram = [],
		array $extra = [],
	): PerfBucket {
		return new PerfBucket(
			$granularity,
			new DateTimeImmutable('2026-03-10 14:37:00'),
			$this->project,
			$serverName,
			$host,
			$path,
			hits: $hits,
			sumDuration: $sumDuration,
			sumUser: $sumUser,
			sumSys: $sumSys,
			maxDuration: $maxDuration,
			sumMem: $sumMem,
			maxMem: $maxMem,
			clientErrors: $clientErrors,
			serverErrors: $serverErrors,
			durationHistogram: $this->histogram($histogram),
			extra: $extra,
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
