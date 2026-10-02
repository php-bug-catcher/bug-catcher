<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Report;

use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\PerfWindow;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * How finely a window is worth reading.
 *
 * Not "as finely as the data allows": two hours of minutes is 120 points, a week of hours is 168
 * and a year of days is 365, which are all charts a person can look at. A week of minutes is ten
 * thousand points drawn into four hundred pixels, and a day of days is one.
 */
final readonly class GranularityResolver
{
	private const int TWO_HOURS = 2 * 3600;

	private const int SEVEN_DAYS = 7 * 86400;

	public function forWindow(DateTimeImmutable $from, DateTimeImmutable $to): PerfGranularity
	{
		$seconds = $to->getTimestamp() - $from->getTimestamp();

		if ($seconds <= 0) {
			throw new InvalidArgumentException(sprintf(
				'A window ends after it starts: %s to %s.',
				$from->format('Y-m-d H:i:s'),
				$to->format('Y-m-d H:i:s'),
			));
		}

		return match (true) {
			$seconds <= self::TWO_HOURS  => PerfGranularity::Minute,
			$seconds <= self::SEVEN_DAYS => PerfGranularity::Hour,
			default                      => PerfGranularity::Day,
		};
	}

	/**
	 * The same decision, with the edges moved onto bucket boundaries: a window read as hours has
	 * to start on an hour, or the first point holds part of a bucket and the chart opens with a
	 * dip that is not there. The end is rounded up for the same reason.
	 */
	public function window(DateTimeImmutable $from, DateTimeImmutable $to): PerfWindow
	{
		$granularity = $this->forWindow($from, $to);
		$end         = $granularity->floor($to);

		if ($end < $to) {
			$end = $end->add($granularity->interval());
		}

		return new PerfWindow($granularity->floor($from), $end, $granularity);
	}
}
