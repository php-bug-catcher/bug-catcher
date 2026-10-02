<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Perf;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\Retention\ChunkedDeleter;
use BugCatcher\Service\Perf\Retention\PurgeService;
use BugCatcher\Service\Perf\Retention\RetentionPolicy;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Zenstruck\Foundry\Test\Factories;

class PurgeServiceTest extends KernelTestCase
{
	use Factories;

	// on a minute boundary, so that a bucket can sit exactly on the cutoff
	private const string NOW = '2026-03-10 15:17:00';

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne()->_real();
	}

	public function testEachGranularityIsPurgedAtItsOwnCutoff(): void
	{
		$this->store(PerfGranularity::Minute, '2026-03-02 10:00:00');
		$this->store(PerfGranularity::Minute, '2026-03-09 10:00:00');
		$this->store(PerfGranularity::Hour, '2025-11-01 10:00:00');
		$this->store(PerfGranularity::Hour, '2026-02-01 10:00:00');
		$this->store(PerfGranularity::Day, '2023-01-01 00:00:00');
		$this->store(PerfGranularity::Day, '2025-01-01 00:00:00');

		$result = $this->service()->purge();

		$this->assertSame(['minute' => 1, 'hour' => 1, 'day' => 1], $result->rows);
		$this->assertSame(3, $result->total());
		$this->assertFalse($result->dryRun);

		$this->assertSame(
			['2026-03-09 10:00:00', '2026-02-01 10:00:00', '2025-01-01 00:00:00'],
			$this->remaining(),
		);
	}

	/** A bucket exactly at the cutoff is still inside the window that is kept. */
	public function testTheCutoffItselfIsKept(): void
	{
		$this->store(PerfGranularity::Minute, '2026-03-03 15:17:00');

		$this->assertSame(0, $this->service()->purge()->total());
		$this->assertCount(1, $this->remaining());
	}

	public function testADryRunCountsWhatItWouldDeleteAndDeletesNothing(): void
	{
		$this->store(PerfGranularity::Minute, '2026-03-02 10:00:00');
		$this->store(PerfGranularity::Minute, '2026-03-01 10:00:00');
		$this->store(PerfGranularity::Hour, '2026-02-01 10:00:00');

		$result = $this->service()->purge(dryRun: true);

		$this->assertSame(['minute' => 2, 'hour' => 0, 'day' => 0], $result->rows);
		$this->assertTrue($result->dryRun);
		$this->assertCount(3, $this->remaining());
	}

	/**
	 * One statement over a table this size would hold locks and inflate the undo log for as long
	 * as it ran, which on a time-series table is a production incident rather than a cron job.
	 */
	public function testRowsAreDeletedInChunksUntilThereAreNoMore(): void
	{
		for ($minute = 0; $minute < 7; $minute++) {
			$this->store(PerfGranularity::Minute, sprintf('2026-03-02 10:%02d:00', $minute));
		}

		$result = $this->service(chunkSize: 2)->purge();

		$this->assertSame(7, $result->total());
		$this->assertSame([], $this->remaining());
	}

	public function testAnExtraMetricGoesWithTheBucketItBelongsTo(): void
	{
		$this->store(PerfGranularity::Minute, '2026-03-02 10:00:00', extra: ['sq' => 10]);

		$this->service()->purge();

		$this->assertSame(0, (int)$this->connection()->fetchOne('SELECT COUNT(*) FROM perf_bucket_extra'));
	}

	public function testThereIsNothingToPurgeInAFreshInstallation(): void
	{
		$result = $this->service()->purge();

		$this->assertSame(0, $result->total());
	}

	private function service(int $chunkSize = 10_000): PurgeService
	{
		$em = self::getContainer()->get(EntityManagerInterface::class);

		return new PurgeService(
			$em,
			self::getContainer()->get(PerfBucketRepository::class),
			new RetentionPolicy(
				['minute' => '7 days', 'hour' => '90 days', 'day' => '2 years'],
				new MockClock(self::NOW),
			),
			new ChunkedDeleter($chunkSize),
			new NullLogger(),
		);
	}

	private function store(
		PerfGranularity $granularity,
		string $bucketAt,
		array $extra = [],
	): void {
		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				$granularity,
				new DateTimeImmutable($bucketAt),
				$this->project,
				'web-01',
				'www.site.com',
				'/user/{id}',
				hits: 1,
				extra: $extra,
			),
		]);
	}

	/** @return list<string> */
	private function remaining(): array
	{
		return array_map(
			static fn(array $row): string => (string)$row['bucket_at'],
			$this->connection()->fetchAllAssociative('SELECT bucket_at FROM perf_bucket ORDER BY bucket_at DESC'),
		);
	}

	private function connection(): Connection
	{
		return self::getContainer()->get(EntityManagerInterface::class)->getConnection();
	}
}
