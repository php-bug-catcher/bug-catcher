<?php

declare(strict_types=1);

namespace BugCatcher\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use BugCatcher\ApiResource\PerfBucketBatch;
use BugCatcher\ApiResource\PerfBucketRow;
use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Repository\ProjectRepository;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use DateTimeZone;
use Generator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Resolves `projectCode` to a `Project` the way {@see LogRecordSaveProcessor} does, then hands the
 * batch to {@see PerfBucketUpserter}.
 *
 * Unlike the record processors there is no `persist_processor` to decorate: `perf_bucket` is
 * written by `INSERT ... ON DUPLICATE KEY UPDATE`, which the ORM cannot express, and the rows are
 * not entities anybody manages. There is no second validation phase either - the batch carries
 * nothing that only becomes checkable once the project is known.
 */
final readonly class PerfBucketBatchProcessor implements ProcessorInterface
{
	public function __construct(
		private ProjectRepository $projectRepository,
		private PerfBucketUpserter $upserter,
		private bool $enabled = true,
	) {
	}

	public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
	{
		if (!$data instanceof PerfBucketBatch) {
			return $data;
		}

		// 503 rather than 404: the collector advances its cursor only on a 2xx, so refusing this
		// way leaves the samples on the monitored machine until performance monitoring is turned
		// back on. The cost of leaving it off is that the local log keeps growing - the fix for
		// that is to stop the collector's cron, not to accept measurements nothing will read.
		if (!$this->enabled) {
			throw new ServiceUnavailableHttpException(message: 'Performance monitoring is disabled.');
		}

		$project = $this->projectRepository->findOneBy(['code' => $data->projectCode]);
		if ($project === null) {
			throw new NotFoundHttpException('Project not found');
		}

		$this->upserter->upsert($this->buckets($data, $project));

		return null;
	}

	/** @return Generator<PerfBucket> */
	private function buckets(PerfBucketBatch $batch, Project $project): Generator
	{
		foreach ($batch->rows as $row) {
			yield $this->bucket($row, $project);
		}
	}

	/**
	 * The collector timestamps a bucket with `gmdate()`, so what arrives is UTC - and every read
	 * in this bundle asks its question in the server's own timezone, because that is the wall
	 * clock the rest of the schema is written in: `record.date` is `new DateTimeImmutable()` and
	 * nothing else converts anything.
	 *
	 * Storing the UTC instant verbatim therefore puts the measurements an offset away from every
	 * window that will ever look for them. On a server two hours ahead of UTC the dashboard asks
	 * for the last hour, the buckets are two hours older than that, and the page is empty -
	 * with the rows sitting in the table the whole time and nothing anywhere reporting an error.
	 *
	 * Converting here rather than at each read keeps one frame of reference in the table: the
	 * roll-up, the retention purge, the detector and the charts all compare like with like, and a
	 * chart axis says the time a person would have seen on their own clock.
	 */
	private function bucket(PerfBucketRow $row, Project $project): PerfBucket
	{
		return new PerfBucket(
			PerfGranularity::Minute,
			$row->bucketAt?->setTimezone(new DateTimeZone(date_default_timezone_get())),
			$project,
			$row->serverName,
			$row->host,
			$row->path,
			hits: $row->hits,
			sumDuration: $row->sumDuration,
			sumUser: $row->sumUser,
			sumSys: $row->sumSys,
			maxDuration: $row->maxDuration,
			sumMem: $row->sumMem,
			maxMem: $row->maxMem,
			clientErrors: $row->clientErrors,
			serverErrors: $row->serverErrors,
			durationHistogram: $row->histogram,
			extra: $row->extra,
		);
	}
}
