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
 *
 * `extra` is the exception to the per-hit rule: those are whatever the monitored application
 * counted, in whatever unit it counted them, **summed over the bucket**. Nothing here knows what
 * they mean, so nothing here can divide them - {@see extraPerHit()} is offered because a count
 * per request is what almost every one of them is asked for.
 */
final readonly class PerfTimePoint
{
	/** @param array<string, float> $extra summed over the bucket, not per hit */
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
		public array $extra = [],
	) {
	}

	public static function empty(DateTimeImmutable $bucketAt): self
	{
		return new self($bucketAt, 0, 0.0, null, 0.0, 0.0, 0.0, 0.0, 0, 0, 0, 0, LatencyBands::empty());
	}

	/**
	 * An extra metric of this bucket divided by its requests.
	 *
	 * Null rather than zero when the application never sent it: a bucket that reported no query
	 * count is not a bucket that ran no queries, and a chart has to be able to tell those apart
	 * or an uninstrumented deploy reads as a fixed application.
	 */
	public function extraPerHit(string $name): ?float
	{
		if ($this->hits === 0 || !isset($this->extra[$name])) {
			return null;
		}

		return $this->extra[$name] / $this->hits;
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
