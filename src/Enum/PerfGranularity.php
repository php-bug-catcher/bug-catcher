<?php

declare(strict_types=1);

namespace BugCatcher\Enum;

use DateInterval;
use DateTimeImmutable;

/**
 * The width of a {@see \BugCatcher\Entity\PerfBucket} row.
 *
 * The three steps are a cascade: minutes roll up into hours, hours into days, and each step is
 * exact for counts, sums, maxima and histograms. {@see coarser()} walks that cascade upwards,
 * {@see finer()} names the granularity a roll-up reads from.
 */
enum PerfGranularity: string
{
	case Minute = 'minute';
	case Hour   = 'hour';
	case Day    = 'day';

	/**
	 * The width of one bucket. A fresh instance every call — DateInterval is mutable and callers
	 * hand it to DateTime::add().
	 */
	public function interval(): DateInterval
	{
		return new DateInterval(match ($this) {
			self::Minute => 'PT1M',
			self::Hour   => 'PT1H',
			self::Day    => 'P1D',
		});
	}

	/** The granularity this one rolls up into, null for the coarsest. */
	public function coarser(): ?self
	{
		return match ($this) {
			self::Minute => self::Hour,
			self::Hour   => self::Day,
			self::Day    => null,
		};
	}

	/** The granularity a roll-up into this one reads from, null for the finest. */
	public function finer(): ?self
	{
		return match ($this) {
			self::Minute => null,
			self::Hour   => self::Minute,
			self::Day    => self::Hour,
		};
	}

	/**
	 * The start of the bucket a timestamp belongs to — the value stored in `bucket_at`, and with
	 * it the unique key. Flooring here rather than at each call site is what keeps ingest and
	 * roll-up agreeing on a boundary.
	 */
	public function floor(DateTimeImmutable $at): DateTimeImmutable
	{
		return match ($this) {
			self::Minute => $at->setTime((int)$at->format('G'), (int)$at->format('i')),
			self::Hour   => $at->setTime((int)$at->format('G'), 0),
			self::Day    => $at->setTime(0, 0),
		};
	}
}
