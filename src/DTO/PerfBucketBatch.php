<?php

declare(strict_types=1);

namespace BugCatcher\DTO;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use BugCatcher\Api\Processor\PerfBucketBatchProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * `POST /api/perf_buckets` - one request per minute per machine, not one per HTTP request.
 *
 * The response is 204 with no body on purpose: nothing was created that anybody can GET, and
 * echoing a batch of a few hundred rows back would be the largest thing in the exchange. The
 * collector only reads the status - on a 2xx it advances its cursor and drops the raw lines, on
 * anything else it keeps them for the next run.
 *
 * `serverName` sits on the row rather than on the batch: a log directory shared between machines
 * can legitimately produce rows for more than one, and it is part of the bucket key either way.
 */
#[ApiResource(
	operations: [
		new Post(
			uriTemplate: '/perf_buckets',
			status: 204,
			output: false,
			processor: PerfBucketBatchProcessor::class,
		),
	],
)]
final class PerfBucketBatch
{
	#[Assert\NotBlank]
	#[Assert\Length(min: 1, max: 50)]
	public ?string $projectCode = null;

	/**
	 * @var PerfBucketRow[]
	 */
	#[Assert\Count(min: 1)]
	#[Assert\Valid]
	public array $rows = [];
}
