<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Rollup;

use BugCatcher\Entity\PerfBucket;

/**
 * What {@see PathCapEnforcer} made of one bucket's rows, and how much of it stopped being a path.
 *
 * The count is the point of the type: folding is a backstop for a normalisation rule that failed
 * to cover a pattern, and a fold nobody is told about is a dashboard that silently stops naming
 * routes.
 */
final readonly class CappedBuckets
{
	/** @param list<PerfBucket> $buckets */
	public function __construct(
		public array $buckets,
		public int $foldedPaths,
	) {
	}
}
