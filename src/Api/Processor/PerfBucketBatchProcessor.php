<?php

declare(strict_types=1);

namespace BugCatcher\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use BugCatcher\DTO\PerfBucketBatch;
use BugCatcher\DTO\PerfBucketRow;
use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Repository\ProjectRepository;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use Generator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
	) {
	}

	public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
	{
		if (!$data instanceof PerfBucketBatch) {
			return $data;
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

	private function bucket(PerfBucketRow $row, Project $project): PerfBucket
	{
		return new PerfBucket(
			PerfGranularity::Minute,
			$row->bucketAt,
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
