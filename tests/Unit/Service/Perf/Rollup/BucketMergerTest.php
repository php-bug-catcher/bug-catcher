<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Rollup;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Rollup\BucketMerger;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BucketMergerTest extends TestCase
{
	private Project $project;

	protected function setUp(): void
	{
		$this->project = new Project();
	}

	public function testCountersAddAndTheResultCarriesTheNameItWasGiven(): void
	{
		$merged = (new BucketMerger())->merge([
			$this->bucket(path: '/a', hits: 5, sumDuration: 2.5, sumUser: 2.0, sumSys: 0.25, sumMem: 100, clientErrors: 1, serverErrors: 2),
			$this->bucket(path: '/b', hits: 3, sumDuration: 1.5, sumUser: 1.0, sumSys: 0.25, sumMem: 50, clientErrors: 2, serverErrors: 1),
		], PerfBucket::OTHER_PATH);

		$this->assertSame(PerfBucket::OTHER_PATH, $merged->getPath());
		$this->assertSame(PerfBucket::hashPath(PerfBucket::OTHER_PATH), $merged->getPathHash());
		$this->assertSame(8, $merged->getHits());
		$this->assertSame(4.0, $merged->getSumDuration());
		$this->assertSame(3.0, $merged->getSumUser());
		$this->assertSame(0.5, $merged->getSumSys());
		$this->assertSame(150, $merged->getSumMem());
		$this->assertSame(3, $merged->getClientErrors());
		$this->assertSame(3, $merged->getServerErrors());
	}

	public function testTheRestOfTheKeyComesFromTheRowsThemselves(): void
	{
		$merged = (new BucketMerger())->merge([$this->bucket(path: '/a')], PerfBucket::OTHER_PATH);

		$this->assertSame(PerfGranularity::Hour, $merged->getGranularity());
		$this->assertSame('2026-03-10 14:00:00', $merged->getBucketAt()->format('Y-m-d H:i:s'));
		$this->assertSame($this->project, $merged->getProject());
		$this->assertSame('web-01', $merged->getServerName());
		$this->assertSame('www.site.com', $merged->getHost());
	}

	/**
	 * A maximum is one request's measurement. Adding two of them would invent a request that never
	 * ran, and taking the last would lose the peak the whole column exists for.
	 */
	public function testAMaximumIsTheGreatestOfThem(): void
	{
		$merged = (new BucketMerger())->merge([
			$this->bucket(path: '/a', maxDuration: 0.9, maxMem: 3000),
			$this->bucket(path: '/b', maxDuration: 1.4, maxMem: 1000),
			$this->bucket(path: '/c', maxDuration: 0.2, maxMem: 2000),
		], PerfBucket::OTHER_PATH);

		$this->assertSame(1.4, $merged->getMaxDuration());
		$this->assertSame(3000, $merged->getMaxMem());
	}

	public function testHistogramsAddBinByBin(): void
	{
		$merged = (new BucketMerger())->merge([
			$this->bucket(path: '/a', histogram: [0 => 2, 8 => 4]),
			$this->bucket(path: '/b', histogram: [8 => 1, 15 => 3]),
		], PerfBucket::OTHER_PATH);

		$this->assertSame($this->histogram([0 => 2, 8 => 5, 15 => 3]), $merged->getDurationHistogram());
	}

	public function testExtraMetricsAddByName(): void
	{
		$merged = (new BucketMerger())->merge([
			$this->bucket(path: '/a', extra: ['sq' => 10, 'st' => 0.5]),
			$this->bucket(path: '/b', extra: ['sq' => 5, 'cache_hits' => 2]),
		], PerfBucket::OTHER_PATH);

		$this->assertSame(['sq' => 15.0, 'st' => 0.5, 'cache_hits' => 2.0], $merged->getExtra());
	}

	public function testThereIsNothingToMergeInAnEmptyList(): void
	{
		$this->expectException(InvalidArgumentException::class);

		(new BucketMerger())->merge([], PerfBucket::OTHER_PATH);
	}

	/**
	 * Everything but the path is the identity of the row being written. Merging across any of it
	 * would not be a roll-up, it would be a row that claims measurements from a machine it never
	 * names - and the upsert would file it under the key of whichever row happened to be first.
	 *
	 * @dataProvider rowsThatDoNotBelongTogether
	 */
	public function testRowsThatDoNotShareTheKeyAreNotMerged(array $second): void
	{
		$this->expectException(InvalidArgumentException::class);

		(new BucketMerger())->merge([
			$this->bucket(path: '/a'),
			$this->bucket(...['path' => '/b'] + $second),
		], PerfBucket::OTHER_PATH);
	}

	/** @return array<string, array{array<string, mixed>}> */
	public static function rowsThatDoNotBelongTogether(): array
	{
		return [
			'another machine'     => [['serverName' => 'web-02']],
			'another vhost'       => [['host' => 'api.site.com']],
			'another granularity' => [['granularity' => PerfGranularity::Day]],
			'another bucket'      => [['bucketAt' => '2026-03-10 15:00:00']],
		];
	}

	public function testRowsOfTwoProjectsAreNotMerged(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$merger = new BucketMerger();
		$mine   = $this->bucket(path: '/a');
		$this->project = new Project();

		$merger->merge([$mine, $this->bucket(path: '/b')], PerfBucket::OTHER_PATH);
	}

	private function bucket(
		string $path,
		PerfGranularity $granularity = PerfGranularity::Hour,
		string $bucketAt = '2026-03-10 14:00:00',
		string $serverName = 'web-01',
		string $host = 'www.site.com',
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
			new DateTimeImmutable($bucketAt),
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
