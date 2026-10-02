<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Retention;

use BugCatcher\Enum\PerfGranularity;
use DateInterval;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Clock\ClockInterface;

/**
 * How long each granularity is kept, as configured under `bug_catcher.perf.retention`.
 *
 * Tiered on purpose: minutes are the bulk of the table and the shortest-lived, and because the
 * cascade into hours and days is exact, dropping them loses nothing a long-range chart would have
 * shown.
 *
 * Every duration is validated when the policy is built rather than when the DELETE runs. What is
 * on the other side of a mistyped value is a statement that removes rows, so `'7 dyas'` has to
 * stop the command, and so does anything - `'0 days'`, `'-7 days'` - that would put the cutoff at
 * or after now and take the whole table with it.
 */
final readonly class RetentionPolicy
{
	/** @var array<string, DateInterval> */
	private array $keep;

	/** @param array<string, string> $retention granularity value => anything `DateInterval` reads */
	public function __construct(array $retention, private ClockInterface $clock)
	{
		$keep = [];
		foreach (PerfGranularity::cases() as $granularity) {
			$keep[$granularity->value] = $this->interval(
				$retention[$granularity->value] ?? throw new InvalidArgumentException(
					sprintf('Nothing says how long %s buckets are kept.', $granularity->value),
				),
				$granularity,
			);
		}

		$this->keep = $keep;
	}

	/** Buckets of this granularity older than the instant returned are no longer kept. */
	public function cutoff(PerfGranularity $granularity): DateTimeImmutable
	{
		return $this->clock->now()->sub($this->keep[$granularity->value]);
	}

	private function interval(string $duration, PerfGranularity $granularity): DateInterval
	{
		try {
			$interval = DateInterval::createFromDateString($duration);
		} catch (Exception $e) {
			throw new InvalidArgumentException(
				sprintf('The retention of %s buckets is not a duration: %s.', $granularity->value, $duration),
				previous: $e,
			);
		}

		$now = $this->clock->now();
		if ($now->sub($interval) >= $now) {
			throw new InvalidArgumentException(sprintf(
				'The retention of %s buckets has to reach into the past, %s does not.',
				$granularity->value,
				$duration,
			));
		}

		return $interval;
	}
}
