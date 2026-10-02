<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Rollup;

use BugCatcher\Entity\Project;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\Ingest\UpsertMode;
use BugCatcher\Service\Perf\PerfWindow;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Minutes into hours and hours into days.
 *
 * The cascade is exact for everything stored - counts and sums add, maxima take the greatest,
 * histogram bins add one by one - which is what lets the finer rows be deleted afterwards without
 * a long-range chart losing anything. The arithmetic happens in SQL, next to the rows
 * ({@see PerfBucketRepository::aggregateInto()}); this orchestrates it.
 *
 * **A target bucket is recomputed, never accumulated.** Every run reads every source row of the
 * bucket again and writes the answer with {@see UpsertMode::Replace}, so running the command
 * twice over the same window is a no-op, and a minute the hook delivered late - its line is
 * written in shutdown, so a request can be logged after its minute was shipped - is picked up by
 * the next run instead of being lost or double-counted.
 */
final readonly class RollupService
{
	public function __construct(
		private PerfBucketRepository $repository,
		private PerfBucketUpserter $upserter,
		private PathCapEnforcer $pathCap,
		private LoggerInterface $logger,
	) {
	}

	public function rollUp(PerfWindow $window): RollupResult
	{
		$target = $window->granularity;
		$source = $target->finer() ?? throw new InvalidArgumentException(sprintf(
			'Nothing rolls up into %s: it is the granularity the collector ships.',
			$target->value,
		));

		$boundaries = 0;
		$written    = 0;
		$folded     = 0;

		foreach ($this->repository->projectsWithBuckets($source, $window->from, $window->to) as $project) {
			foreach ($this->repository->boundariesToRollUp($window, $project) as $boundary) {
				$capped = $this->pathCap->enforce($this->repository->aggregateInto($target, $boundary, $project));

				$boundaries++;
				$written += $this->upserter->upsert($capped->buckets, UpsertMode::Replace);
				$folded  += $capped->foldedPaths;

				if ($capped->foldedPaths > 0) {
					$this->report($project, $boundary, $capped);
				}
			}
		}

		return new RollupResult($boundaries, $written, $folded);
	}

	/**
	 * Folding is a backstop, not a feature: it means a route pattern got past `PathNormalizer` on
	 * the monitored machine, and the only way anyone finds out is this line.
	 */
	private function report(Project $project, DateTimeImmutable $boundary, CappedBuckets $capped): void
	{
		$this->logger->warning(
			'Performance roll-up folded {paths} paths of project {project} in the bucket starting {bucket}'
			. ' - a normalisation rule is missing on the monitored machine.',
			[
				'paths'   => $capped->foldedPaths,
				'project' => $project->getCode(),
				'bucket'  => $boundary->format('Y-m-d H:i:s'),
			],
		);
	}
}
