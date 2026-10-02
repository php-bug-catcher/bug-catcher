<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Rollup;

use BugCatcher\Entity\PerfBucket;

/**
 * Keeps one bucket down to a workable number of rows.
 *
 * A path that `PathNormalizer` failed to cover - an id the rules did not recognise, a cache
 * buster, a scanner walking an endpoint - arrives as a distinct route and would get a row of its
 * own in every bucket from here to the retention cutoff. Beyond `perf.rollup_path_cap` paths the
 * tail is folded into a single `{@see PerfBucket::OTHER_PATH}` row: the traffic stays in the
 * totals, the cardinality stops growing, and the folded count is what tells an operator to go fix
 * the rule.
 *
 * The cap applies per machine and vhost, because those are part of the key and a fold across them
 * would be a row claiming measurements from a machine it does not name. The overflow row itself
 * is never counted against the cap and never folded away - it is the fold.
 */
final readonly class PathCapEnforcer
{
	public function __construct(
		private BucketMerger $merger,
		private int $cap,
	) {
	}

	/** @param list<PerfBucket> $buckets every row of one target bucket of one project */
	public function enforce(array $buckets): CappedBuckets
	{
		$kept   = [];
		$folded = 0;

		foreach ($this->byMachine($buckets) as $rows) {
			[$keptRows, $foldedPaths] = $this->capOne($rows);

			$kept   = [...$kept, ...$keptRows];
			$folded += $foldedPaths;
		}

		return new CappedBuckets($kept, $folded);
	}

	/**
	 * @param non-empty-list<PerfBucket> $rows
	 * @return array{list<PerfBucket>, int}
	 */
	private function capOne(array $rows): array
	{
		$overflow = array_values(array_filter($rows, $this->isOverflow(...)));
		$paths    = array_values(array_filter($rows, fn(PerfBucket $row): bool => !$this->isOverflow($row)));

		if (count($paths) <= $this->cap) {
			return [[...$paths, ...$overflow], 0];
		}

		// busiest first, and by name where the traffic is equal, so that two runs over the same
		// data fold the same rows
		usort($paths, static fn(PerfBucket $a, PerfBucket $b): int
			=> [$b->getHits(), $a->getPath()] <=> [$a->getHits(), $b->getPath()]);

		$tail = array_slice($paths, $this->cap);

		return [
			[
				...array_slice($paths, 0, $this->cap),
				$this->merger->merge([...$tail, ...$overflow], PerfBucket::OTHER_PATH),
			],
			count($tail),
		];
	}

	private function isOverflow(PerfBucket $bucket): bool
	{
		return $bucket->getPath() === PerfBucket::OTHER_PATH;
	}

	/**
	 * @param list<PerfBucket> $buckets
	 * @return array<string, non-empty-list<PerfBucket>>
	 */
	private function byMachine(array $buckets): array
	{
		$groups = [];
		foreach ($buckets as $bucket) {
			// \x1f rather than a printable separator: a vhost is whatever the request said it was
			$groups[$bucket->getServerName() . "\x1f" . $bucket->getHost()][] = $bucket;
		}

		return $groups;
	}
}
