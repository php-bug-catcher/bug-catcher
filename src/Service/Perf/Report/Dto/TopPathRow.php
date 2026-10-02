<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Report\Dto;

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
	public function __construct(
		public string $label,
		public int $hits,
		public float $totalMs,
		public float $userMs,
		public float $sysMs,
		public int $totalMem,
		public int $maxMem,
		public ?float $p95Ms,
		public float $errorRate,
	) {
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

	/** Wallclock that was not CPU: the database, the cache, the network. */
	public function waitMsPerHit(): float
	{
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
