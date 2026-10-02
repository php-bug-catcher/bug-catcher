<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection;

use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\PerfWindow;
use DateInterval;
use InvalidArgumentException;
use Symfony\Component\Clock\ClockInterface;

/**
 * The stretch of minutes `app:perf:detect` looks at: the last completed ones.
 *
 * The minute still filling up is left out, because it holds a fraction of its traffic and is the
 * shortest road to an alert on a route that is perfectly fine.
 *
 * The width belongs to the command rather than to the configuration, because it has to match the
 * cron interval and that is written in the same line - `*&#47;5 * * * * app:perf:detect` examines
 * five minutes, and anything else leaves gaps nobody looks at.
 */
final readonly class DetectionWindowResolver
{
	public function __construct(private ClockInterface $clock)
	{
	}

	public function lastCompleted(int $minutes): PerfWindow
	{
		if ($minutes < 1) {
			throw new InvalidArgumentException(sprintf('A detection window is at least a minute, not %d.', $minutes));
		}

		$to = PerfGranularity::Minute->floor($this->clock->now());

		return new PerfWindow($to->sub(new DateInterval('PT' . $minutes . 'M')), $to, PerfGranularity::Minute);
	}
}
