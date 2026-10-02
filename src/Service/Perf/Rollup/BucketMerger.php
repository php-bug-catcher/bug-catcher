<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Rollup;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Service\Perf\Histogram\HistogramMath;
use InvalidArgumentException;

/**
 * Several rows of one bucket folded into a single row under a different path.
 *
 * This is how {@see PathCapEnforcer} builds its `__other__` row, and it is the only place in the
 * module where measurements are combined in PHP rather than by the database - the roll-up itself
 * groups in SQL, where the sums belong.
 *
 * Everything except the path is the identity of the row being written, so rows that disagree on
 * any of it are refused rather than quietly folded: a merged row claiming measurements from a
 * machine it does not name is a wrong answer that nothing downstream could detect.
 */
final class BucketMerger
{
	/**
	 * @param non-empty-list<PerfBucket> $buckets rows of one granularity, bucket, project, machine
	 *     and vhost
	 * @param string $path what the merged row is called
	 */
	public function merge(array $buckets, string $path): PerfBucket
	{
		$first = $buckets[0] ?? throw new InvalidArgumentException('There is nothing to merge in an empty list.');
		$this->assertOneBucket($buckets, $first);

		$hits         = 0;
		$sumDuration  = 0.0;
		$sumUser      = 0.0;
		$sumSys       = 0.0;
		$maxDuration  = 0.0;
		$sumMem       = 0;
		$maxMem       = 0;
		$clientErrors = 0;
		$serverErrors = 0;
		$extra        = [];

		foreach ($buckets as $bucket) {
			$hits         += $bucket->getHits();
			$sumDuration  += $bucket->getSumDuration();
			$sumUser      += $bucket->getSumUser();
			$sumSys       += $bucket->getSumSys();
			$sumMem       += $bucket->getSumMem();
			$clientErrors += $bucket->getClientErrors();
			$serverErrors += $bucket->getServerErrors();
			$maxDuration  = max($maxDuration, $bucket->getMaxDuration());
			$maxMem       = max($maxMem, $bucket->getMaxMem());

			foreach ($bucket->getExtra() as $name => $value) {
				$extra[$name] = ($extra[$name] ?? 0.0) + $value;
			}
		}

		return new PerfBucket(
			$first->getGranularity(),
			$first->getBucketAt(),
			$first->getProject(),
			$first->getServerName(),
			$first->getHost(),
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
			durationHistogram: HistogramMath::sum(array_map(
				static fn(PerfBucket $bucket): array => $bucket->getDurationHistogram(),
				$buckets,
			)),
			extra: $extra,
		);
	}

	/** @param list<PerfBucket> $buckets */
	private function assertOneBucket(array $buckets, PerfBucket $first): void
	{
		foreach ($buckets as $bucket) {
			$differs = match (true) {
				$bucket->getGranularity() !== $first->getGranularity()   => 'granularity',
				$bucket->getBucketAt() != $first->getBucketAt()          => 'bucket',
				$bucket->getProject() !== $first->getProject()           => 'project',
				$bucket->getServerName() !== $first->getServerName()     => 'machine',
				$bucket->getHost() !== $first->getHost()                 => 'vhost',
				default                                                  => null,
			};

			if ($differs !== null) {
				throw new InvalidArgumentException(sprintf(
					'Rows of a different %s are not one bucket: %s and %s.',
					$differs,
					$first->getPath(),
					$bucket->getPath(),
				));
			}
		}
	}
}
