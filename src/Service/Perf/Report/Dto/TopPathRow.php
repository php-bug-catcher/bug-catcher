<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Report\Dto;

use BugCatcher\Entity\PerfBucket;

/**
 * One line of the table phptop prints, over a window of buckets.
 *
 * The totals are cumulative and the per-hit numbers are derived, so the "per hit or in total"
 * toggle on the page is presentation rather than two different reports. `label` is whatever the
 * report grouped by - a route, a vhost or a machine - because a row grouped by machine has no one
 * path to name.
 */
final readonly class TopPathRow
{
	/**
	 * @param float $maxMs the slowest single request on this row. The only number here that is
	 *     not an aggregate, and the only one that can describe a run past the histogram's top
	 *     bin: `p95Ms` saturates at sixty seconds, because nothing bounds that bin from above and
	 *     its lower edge is the only honest estimate. An eighteen-minute cron job is a p95 of
	 *     exactly 60000 and a `maxMs` of 1081002.
	 * @param array<string, float> $extra totals over the window, in whatever unit the application counted
	 */
	public function __construct(
		public string $label,
		public int $hits,
		public float $totalMs,
		public float $userMs,
		public float $sysMs,
		public int $totalMem,
		public int $maxMem,
		public float $maxMs,
		public ?float $p95Ms,
		public float $errorRate,
		public array $extra = [],
	) {
	}

	/**
	 * A fragment identifier for this row, so a link can scroll to it.
	 *
	 * The same hash the buckets are keyed by, because a route is not a URL fragment: `/user/{id}`
	 * has a brace in it and `/` is a path separator.
	 *
	 * Only meaningful when the report grouped by path - under any other grouping `label` is a
	 * machine or a vhost, and the template is what knows which it asked for.
	 */
	public function anchor(): string
	{
		return PerfBucket::hashPath($this->label);
	}

	/** The total an application counted on this row, or null if it never sent that metric. */
	public function extraTotal(string $name): ?float
	{
		return $this->extra[$name] ?? null;
	}

	/** The same per request. Null for the same reason {@see PerfTimePoint::extraPerHit()} is. */
	public function extraPerHit(string $name): ?float
	{
		$total = $this->extraTotal($name);

		return $total === null ? null : $this->perHit($total);
	}

	public function msPerHit(): float
	{
		return $this->perHit($this->totalMs);
	}

	public function userMsPerHit(): float
	{
		return $this->perHit($this->userMs);
	}

	public function sysMsPerHit(): float
	{
		return $this->perHit($this->sysMs);
	}

	/**
	 * Wallclock that was not CPU: the database, the cache, the network.
	 *
	 * Null where the machine reported no CPU at all - see {@see PerfTimePoint::$waitMs} for why
	 * that is not the same as a request that spent all its time waiting.
	 */
	public function waitMsPerHit(): ?float
	{
		if ($this->userMs <= 0.0 && $this->sysMs <= 0.0) {
			return null;
		}

		return max(0.0, $this->msPerHit() - $this->userMsPerHit() - $this->sysMsPerHit());
	}

	public function memPerHit(): float
	{
		return $this->perHit((float)$this->totalMem);
	}

	private function perHit(float $total): float
	{
		return $this->hits === 0 ? 0.0 : $total / $this->hits;
	}
}
