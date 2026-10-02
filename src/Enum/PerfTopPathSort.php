<?php

declare(strict_types=1);

namespace BugCatcher\Enum;

use BugCatcher\Service\Perf\Report\Dto\TopPathRow;

/**
 * What the top-paths table is heaviest by - the same choices phptop exposes as command line
 * flags, as controls on a page.
 *
 * The values are what a `LiveProp` carries in a URL, so they are short and stable.
 */
enum PerfTopPathSort: string
{
	case Hits      = 'hits';
	case TotalTime = 'total';
	case User      = 'user';
	case Sys       = 'sys';
	case MemPerHit = 'mem';
	case MaxMem    = 'max_mem';
	case P95       = 'p95';

	/**
	 * What this sort reads off a row. Here rather than in the report builder because the enum is
	 * the one place that already knows what each value means.
	 */
	public function valueOf(TopPathRow $row): float
	{
		return match ($this) {
			self::Hits      => (float)$row->hits,
			self::TotalTime => $row->totalMs,
			self::User      => $row->userMs,
			self::Sys       => $row->sysMs,
			self::MemPerHit => $row->memPerHit(),
			self::MaxMem    => (float)$row->maxMem,
			self::P95       => $row->p95Ms ?? 0.0,
		};
	}

	public function label(): string
	{
		return match ($this) {
			self::Hits      => 'Hits',
			self::TotalTime => 'Total time',
			self::User      => 'User CPU',
			self::Sys       => 'System CPU',
			self::MemPerHit => 'Memory per hit',
			self::MaxMem    => 'Peak memory',
			self::P95       => 'p95 latency',
		};
	}
}
