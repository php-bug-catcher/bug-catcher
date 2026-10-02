<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Perf;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
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
	 * MySQL does JSON arithmetic in DOUBLE, so without a cast a merged histogram comes back as
	 * `[2.0, ...]` and every reader that expects counts breaks.
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
	 * Unlike the histogram, an extra metric is not cast on the way in: one application's extra key
	 * counts queries and another's times them, and there is no single cast that is right for both.
	 * So a merged value reads back as a float, which `extra` is typed for.
	 */
	public function testAMergedExtraMetricReadsBackAsAFloat(): void
	{
		$this->upserter()->upsert([$this->bucket(extra: ['sq' => 10])]);
		$this->upserter()->upsert([$this->bucket(extra: ['sq' => 5])]);

		$this->assertSame(['sq' => 15.0], $this->stored()->getExtra());
	}

	public function testABatchWithoutExtraLeavesTheStoredExtraAlone(): void
	{
		$this->upserter()->upsert([$this->bucket(extra: ['sq' => 10])]);
		$this->upserter()->upsert([$this->bucket(hits: 1)]);

		$this->assertEquals(['sq' => 10], $this->stored()->getExtra());
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
		?array $extra = null,
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
