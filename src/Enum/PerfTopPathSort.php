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
	case Hits        = 'hits';
	case TotalTime   = 'total';
	case User        = 'user';
	case Sys         = 'sys';
	case MemPerHit   = 'mem';
	case MaxMem      = 'max_mem';
	case MaxDuration = 'max_time';
	case P95         = 'p95';

	/**
	 * What this sort reads off a row. Here rather than in the report builder because the enum is
	 * the one place that already knows what each value means.
	 */
	public function valueOf(TopPathRow $row): float
	{
		return match ($this) {
			self::Hits        => (float)$row->hits,
			self::TotalTime   => $row->totalMs,
			self::User        => $row->userMs,
			self::Sys         => $row->sysMs,
			self::MemPerHit   => $row->memPerHit(),
			self::MaxMem      => (float)$row->maxMem,
			self::MaxDuration => $row->maxMs,
			self::P95         => $row->p95Ms ?? 0.0,
		};
	}

	/**
	 * The same thing as a SQL expression, so that the `LIMIT` which keeps the table to a screenful
	 * takes the rows this sort is about rather than the busiest ones.
	 *
	 * That distinction is the whole point of this method. A console-heavy project has hundreds of
	 * routes with one hit each, and the slowest of them would never appear in the twenty busiest.
	 *
	 * `{field}` is a mapped field name; the repository is what knows the column it lives in,
	 * because the naming strategy belongs to the application.
	 *
	 * **Null for {@see self::P95}**, and not for want of trying: a percentile of percentiles is
	 * not a percentile, so p95 is estimated in PHP from the bins *after* they have been summed.
	 * A table sorted by p95 is therefore the slowest of the busiest, which is what it has always
	 * been - and now the only sort of which that is true.
	 */
	public function orderBy(): ?string
	{
		return match ($this) {
			self::Hits        => 'SUM({hits})',
			self::TotalTime   => 'SUM({sumDuration})',
			self::User        => 'SUM({sumUser})',
			self::Sys         => 'SUM({sumSys})',
			// SUM(hits) cannot be zero: the ingest API refuses a row with fewer than one hit, so
			// every grouped row has at least one.
			self::MemPerHit   => 'SUM({sumMem}) / SUM({hits})',
			self::MaxMem      => 'MAX({maxMem})',
			self::MaxDuration => 'MAX({maxDuration})',
			self::P95         => null,
		};
	}

	public function label(): string
	{
		return match ($this) {
			self::Hits        => 'Hits',
			self::TotalTime   => 'Total time',
			self::User        => 'User CPU',
			self::Sys         => 'System CPU',
			self::MemPerHit   => 'Memory per hit',
			self::MaxMem      => 'Peak memory',
			self::MaxDuration => 'Slowest run',
			self::P95         => 'p95 latency',
		};
	}
}
