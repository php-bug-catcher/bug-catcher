<?php

declare(strict_types=1);

namespace BugCatcher\DTO;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * One route on one machine over one minute, as the collector puts it on the wire.
 *
 * Validation here is the edge of the system: these numbers arrive over HTTP from a machine the
 * server does not control, and every one of them ends up inside an arithmetic SQL statement. The
 * constraints are what keeps `perf_bucket` holding measurements rather than whatever was posted.
 *
 * `granularity` is not a field. What the collector ships is always a minute; the hour and day rows
 * are computed by `app:perf:rollup`, and letting a client name a granularity would let it write
 * rows the roll-up believes it produced itself.
 */
final readonly class PerfBucketRow
{
	/** @param list<int> $histogram @param array<string, int|float> $extra */
	public function __construct(
		#[Assert\NotNull]
		public ?DateTimeImmutable $bucketAt = null,

		/** The machine. Empty when the hook could not read a hostname. */
		#[Assert\Length(max: 64)]
		public string $serverName = '',

		/** The vhost. Empty for a CLI run. */
		#[Assert\Length(max: 255)]
		public string $host = '',

		#[Assert\NotBlank]
		#[Assert\Length(max: 1024)]
		public string $path = '',

		#[Assert\GreaterThanOrEqual(1)]
		public int $hits = 0,

		#[Assert\GreaterThanOrEqual(0)]
		public float $sumDuration = 0.0,

		#[Assert\GreaterThanOrEqual(0)]
		public float $sumUser = 0.0,

		#[Assert\GreaterThanOrEqual(0)]
		public float $sumSys = 0.0,

		#[Assert\GreaterThanOrEqual(0)]
		public float $maxDuration = 0.0,

		#[Assert\GreaterThanOrEqual(0)]
		public int $sumMem = 0,

		#[Assert\GreaterThanOrEqual(0)]
		public int $maxMem = 0,

		#[Assert\GreaterThanOrEqual(0)]
		public int $clientErrors = 0,

		#[Assert\GreaterThanOrEqual(0)]
		public int $serverErrors = 0,

		#[Assert\Count(exactly: HistogramBins::COUNT)]
		#[Assert\All([new Assert\Type('integer'), new Assert\GreaterThanOrEqual(0)])]
		public array $histogram = [],

		#[Assert\All([new Assert\Type('numeric')])]
		public array $extra = [],
	) {
	}

	/**
	 * The collector counts a response as either a client error or a server error, never both, so
	 * more errors than hits means the row was not produced by counting requests.
	 */
	#[Assert\Callback]
	public function validateErrorsFitTheHits(ExecutionContextInterface $context): void
	{
		if ($this->clientErrors + $this->serverErrors > $this->hits) {
			$context->buildViolation('A bucket cannot hold more errors than hits.')
				->atPath('clientErrors')
				->addViolation();
		}
	}

	/**
	 * Extra metric names reach SQL as JSON paths, so they are checked at the edge as well as in
	 * the upserter - here it is a 422 the collector can read, there it is a last line of defence.
	 */
	#[Assert\Callback]
	public function validateExtraNames(ExecutionContextInterface $context): void
	{
		foreach (array_keys($this->extra) as $name) {
			if (!is_string($name) || preg_match(PerfBucket::EXTRA_NAME_PATTERN, $name) !== 1) {
				$context->buildViolation('Extra metric name "{{ name }}" is not usable: letters, digits, underscore, dot and dash, up to 32 characters.')
					->setParameter('{{ name }}', (string)$name)
					->atPath('extra')
					->addViolation();
			}
		}
	}
}
