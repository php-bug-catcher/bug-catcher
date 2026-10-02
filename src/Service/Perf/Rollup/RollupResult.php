<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Rollup;

use BugCatcher\Entity\PerfBucket;

/**
 * What one run of `app:perf:rollup` did, in the three numbers worth printing from cron.
 *
 * `foldedPaths` is the one to watch: anything above zero means a route pattern is escaping
 * normalisation and the rules on the monitored machine want a look.
 */
final readonly class RollupResult
{
	public function __construct(
		public int $boundaries,
		public int $rowsWritten,
		public int $foldedPaths,
	) {
	}

	public function summary(): string
	{
		$summary = sprintf('%d buckets, %d rows', $this->boundaries, $this->rowsWritten);

		return $this->foldedPaths === 0
			? $summary
			: sprintf('%s, %d paths folded into %s', $summary, $this->foldedPaths, PerfBucket::OTHER_PATH);
	}
}
