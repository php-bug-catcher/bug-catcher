<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Report\Dto;

use DateTimeImmutable;

/**
 * One point of every chart on the page: what happened in one bucket.
 *
 * Durations are **milliseconds per hit** rather than the seconds the table stores, because that is
 * what a chart axis says and what a person compares. `userMs`, `sysMs` and `waitMs` therefore add
 * up to `avgMs`, which is what lets the CPU breakdown be drawn as a stack.
 *
 * `waitMs` is the band worth having: wallclock minus CPU is time spent waiting on the database,
 * on a cache, on an HTTP call. It is usually the largest part of a slow request and no PHP
 * profiler on a production box will tell you about it.
 */
final readonly class PerfTimePoint
{
	public function __construct(
		public DateTimeImmutable $bucketAt,
		public int $hits,
		public float $avgMs,
		public ?float $p95Ms,
		public float $userMs,
		public float $sysMs,
		public float $waitMs,
		public float $memPerHit,
		public int $maxMem,
		public int $ok,
		public int $clientErrors,
		public int $serverErrors,
		public LatencyBands $bands,
	) {
	}

	public static function empty(DateTimeImmutable $bucketAt): self
	{
		return new self($bucketAt, 0, 0.0, null, 0.0, 0.0, 0.0, 0.0, 0, 0, 0, 0, LatencyBands::empty());
	}

	public function errorRate(): float
	{
		return $this->hits === 0 ? 0.0 : ($this->clientErrors + $this->serverErrors) / $this->hits;
	}

	/**
	 * 2xx and 3xx together, then 4xx and 5xx, in the order they are stacked.
	 *
	 * Three bands and not four: the collector counts client and server errors and nothing else, so
	 * a redirect is indistinguishable from a success on the wire. Splitting them would mean a
	 * fifth counter in every bucket of every minute for a line nobody reads.
	 *
	 * @return array<string, int>
	 */
	public function statusMix(): array
	{
		return [
			'2xx / 3xx' => $this->ok,
			'4xx'       => $this->clientErrors,
			'5xx'       => $this->serverErrors,
		];
	}
}
