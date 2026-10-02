<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Retention;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Repository\PerfBucketRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Applies {@see RetentionPolicy} to `perf_bucket`, one granularity at a time.
 *
 * Deleting minutes after a week is not a loss: the cascade into hours and days is exact, so every
 * chart beyond the minute window draws the same line either way. What it buys is a table that
 * stops growing.
 *
 * Extra metrics go with their bucket through the foreign key, so there is nothing to delete in
 * `perf_bucket_extra` here. Column names come from the mapping rather than from literals - the
 * naming strategy belongs to the application, the same reason
 * {@see \BugCatcher\Service\Perf\Ingest\PerfBucketUpserter} builds its statement that way.
 */
final readonly class PurgeService
{
	public function __construct(
		private EntityManagerInterface $em,
		private PerfBucketRepository $repository,
		private RetentionPolicy $policy,
		private ChunkedDeleter $deleter,
		private LoggerInterface $logger,
	) {
	}

	/** @param bool $dryRun count what is beyond the cutoff instead of deleting it */
	public function purge(bool $dryRun = false): PurgeResult
	{
		$rows = [];

		foreach (PerfGranularity::cases() as $granularity) {
			$cutoff = $this->policy->cutoff($granularity);

			$rows[$granularity->value] = $dryRun
				? $this->repository->countOlderThan($granularity, $cutoff)
				: $this->delete($granularity, $cutoff);
		}

		$result = new PurgeResult($rows, $dryRun);
		$this->logger->info('Performance retention: {summary}.', ['summary' => $result->summary()]);

		return $result;
	}

	private function delete(PerfGranularity $granularity, DateTimeImmutable $cutoff): int
	{
		$metadata = $this->em->getClassMetadata(PerfBucket::class);

		return $this->deleter->delete(
			$this->em->getConnection(),
			$metadata->getTableName(),
			sprintf(
				'%s = ? AND %s < ?',
				$metadata->getColumnName('granularity'),
				$metadata->getColumnName('bucketAt'),
			),
			[$granularity->value, $cutoff],
			[1 => Types::DATETIME_IMMUTABLE],
		);
	}
}
