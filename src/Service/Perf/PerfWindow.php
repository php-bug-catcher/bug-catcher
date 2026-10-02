<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf;

use BugCatcher\Enum\PerfGranularity;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A stretch of time and the width of the buckets read over it.
 *
 * `[from, to)` - the end is exclusive, which is what lets consecutive windows tile instead of
 * overlap. Everything in the module that asks a question about a period asks it with one of these:
 * the roll-up computes the target buckets inside it, the detector compares it against the same
 * window on previous days, the report draws it.
 *
 * It lives here rather than under `Report/Dto` because it is not a report concept; the roll-up
 * needs it first.
 */
final readonly class PerfWindow
{
	public function __construct(
		public DateTimeImmutable $from,
		public DateTimeImmutable $to,
		public PerfGranularity $granularity,
	) {
		if ($from >= $to) {
			throw new InvalidArgumentException(sprintf(
				'A window ends after it starts: %s to %s.',
				$from->format('Y-m-d H:i:s'),
				$to->format('Y-m-d H:i:s'),
			));
		}
	}

	public function contains(DateTimeImmutable $at): bool
	{
		return $at >= $this->from && $at < $this->to;
	}
}
