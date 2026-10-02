<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Rollup;

use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\PerfWindow;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Clock\ClockInterface;

/**
 * What `app:perf:rollup` was asked to recompute, as a window of whole buckets.
 *
 * Two rules, and both of them are about what an operator means rather than about arithmetic. A
 * bound lands on the bucket that contains it, so `--to=2026-03-05` with `--granularity=day`
 * includes the fifth. And with no bounds at all the window is the **last completed** bucket: the
 * one still filling up is left for the next run, so what is stored for an hour is always the whole
 * hour.
 */
final readonly class RollupWindowResolver
{
	public function __construct(private ClockInterface $clock)
	{
	}

	/**
	 * @param string|null $from anything `DateTimeImmutable` parses, or null for "as far back as
	 *     `$to`"
	 * @param string|null $to anything `DateTimeImmutable` parses, or null for "up to the last
	 *     completed bucket"
	 */
	public function resolve(PerfGranularity $target, ?string $from = null, ?string $to = null): PerfWindow
	{
		if ($target->finer() === null) {
			throw new InvalidArgumentException(sprintf(
				'Nothing rolls up into %s: it is the granularity the collector ships.',
				$target->value,
			));
		}

		$lastCompleted = $target->floor($this->clock->now());

		// the bucket a bound names is part of the window, so the end is its *next* boundary
		$end = $to === null
			? $lastCompleted
			: $target->floor($this->parse($to, '--to'))->add($target->interval());

		$start = $from === null
			? $end->sub($target->interval())
			: $target->floor($this->parse($from, '--from'));

		return new PerfWindow($start, $end, $target);
	}

	private function parse(string $value, string $option): DateTimeImmutable
	{
		try {
			return new DateTimeImmutable($value, $this->clock->now()->getTimezone());
		} catch (Exception $e) {
			throw new InvalidArgumentException(
				sprintf('%s is not a date: %s.', $option, $value),
				previous: $e,
			);
		}
	}
}
