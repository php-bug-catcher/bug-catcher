<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Entity;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class PerfBucketTest extends TestCase
{
	public function testTheRowKnowsWhatItMeasured(): void
	{
		$project = new Project();
		$bucket  = new PerfBucket(
			PerfGranularity::Minute,
			new DateTimeImmutable('2026-03-10 14:37:00'),
			$project,
			'web-01',
			'www.site.com',
			'/user/{id}',
			hits: 5,
			sumDuration: 2.19,
			sumUser: 1.8,
			sumSys: 0.12,
			maxDuration: 0.93,
			sumMem: 157286400,
			maxMem: 33554432,
			clientErrors: 1,
			serverErrors: 0,
			durationHistogram: $this->histogram([8 => 4, 9 => 1]),
			extra: ['sq' => 42, 'st' => 0.08],
		);

		$this->assertNull($bucket->getId());
		$this->assertSame(PerfGranularity::Minute, $bucket->getGranularity());
		$this->assertSame($project, $bucket->getProject());
		$this->assertSame('web-01', $bucket->getServerName());
		$this->assertSame('www.site.com', $bucket->getHost());
		$this->assertSame('/user/{id}', $bucket->getPath());
		$this->assertSame(5, $bucket->getHits());
		$this->assertSame(2.19, $bucket->getSumDuration());
		$this->assertSame(1.8, $bucket->getSumUser());
		$this->assertSame(0.12, $bucket->getSumSys());
		$this->assertSame(0.93, $bucket->getMaxDuration());
		$this->assertSame(157286400, $bucket->getSumMem());
		$this->assertSame(33554432, $bucket->getMaxMem());
		$this->assertSame(1, $bucket->getClientErrors());
		$this->assertSame(0, $bucket->getServerErrors());
		$this->assertSame($this->histogram([8 => 4, 9 => 1]), $bucket->getDurationHistogram());
		$this->assertSame(['sq' => 42, 'st' => 0.08], $bucket->getExtra());
	}

	public function testAFreshBucketHasMeasuredNothingYet(): void
	{
		$bucket = $this->bucket();

		$this->assertSame(0, $bucket->getHits());
		$this->assertSame(0.0, $bucket->getSumDuration());
		$this->assertSame(0.0, $bucket->getSumUser());
		$this->assertSame(0.0, $bucket->getSumSys());
		$this->assertSame(0.0, $bucket->getMaxDuration());
		$this->assertSame(0, $bucket->getSumMem());
		$this->assertSame(0, $bucket->getMaxMem());
		$this->assertSame(0, $bucket->getClientErrors());
		$this->assertSame(0, $bucket->getServerErrors());
		$this->assertSame(HistogramBins::empty(), $bucket->getDurationHistogram());
		$this->assertNull($bucket->getExtra());
	}

	/**
	 * `bucket_at` is part of the unique key that carries idempotency, so a timestamp that is not on
	 * a bucket boundary would quietly open a second row for the same minute.
	 */
	public function testTheTimestampIsPutOnTheBucketBoundary(): void
	{
		$bucket = $this->bucket(
			granularity: PerfGranularity::Hour,
			bucketAt: new DateTimeImmutable('2026-03-10 14:37:41'),
		);

		$this->assertSame('2026-03-10 14:00:00', $bucket->getBucketAt()->format('Y-m-d H:i:s'));
	}

	public function testThePathHashIsTheOneTheUpserterComputes(): void
	{
		$bucket = $this->bucket(path: '/user/{id}');

		$this->assertSame(PerfBucket::hashPath('/user/{id}'), $bucket->getPathHash());
		$this->assertSame(32, strlen($bucket->getPathHash()));
	}

	public function testPathsThatDifferHashDifferently(): void
	{
		$this->assertNotSame(PerfBucket::hashPath('/a'), PerfBucket::hashPath('/b'));
	}

	public function testAnEmptyExtraIsStoredAsNothingAtAll(): void
	{
		$this->assertNull($this->bucket(extra: [])->getExtra());
	}

	public function testAMalformedHistogramIsRefusedRatherThanStored(): void
	{
		$this->expectException(\InvalidArgumentException::class);

		$this->bucket(durationHistogram: [1, 2, 3]);
	}

	public function testOtherIsTheNameTheRollupFoldsTheLongTailInto(): void
	{
		$this->assertSame('__other__', PerfBucket::OTHER_PATH);
	}

	private function bucket(
		PerfGranularity $granularity = PerfGranularity::Minute,
		?DateTimeImmutable $bucketAt = null,
		string $path = '/',
		?array $durationHistogram = null,
		?array $extra = null,
	): PerfBucket {
		return new PerfBucket(
			$granularity,
			$bucketAt ?? new DateTimeImmutable('2026-03-10 14:37:00'),
			new Project(),
			'web-01',
			'www.site.com',
			$path,
			durationHistogram: $durationHistogram,
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
