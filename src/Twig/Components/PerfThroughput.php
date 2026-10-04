<?php

declare(strict_types=1);

namespace BugCatcher\Twig\Components;

use BugCatcher\Service\Perf\Report\Dto\PerfHealth;
use BugCatcher\Service\Perf\Report\ProjectHealthProvider;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Requests per minute - the one number on the row that goes *down* when something breaks.
 *
 * Everything else here is a measure of how badly the application is behaving, and all of them
 * look wonderful on an application nobody can reach: no traffic is no errors, no slow requests
 * and a perfect Apdex. A collapsed throughput is often the first and only visible sign of a bad
 * deploy, an expired certificate or a load balancer that stopped sending anything through.
 *
 * Per minute rather than a total, so that a row reading an hour and a row reading a day are the
 * same number for the same traffic.
 *
 * Opt-in: add `PerfThroughput` to `bug_catcher.perf_status_list_components`.
 */
#[AsTwigComponent]
final class PerfThroughput extends AbsComponent
{
	public int $hours = 1;

	public function __construct(private readonly ProjectHealthProvider $health)
	{
	}

	public function getHealth(): PerfHealth
	{
		return $this->health->forProject($this->project, $this->hours);
	}

	/**
	 * Short enough for a dashboard cell: `9`, `340`, `1.2k`.
	 *
	 * Rounded to whole requests below a thousand rather than to a decimal - "0.7/min" is a
	 * precision this row does not have and cannot use.
	 */
	public function getFormatted(): string
	{
		$perMinute = $this->getHealth()->requestsPerMinute();

		return match (true) {
			$perMinute === null  => '–',
			$perMinute >= 1000.0 => round($perMinute / 1000, 1) . 'k',
			$perMinute >= 1.0    => (string)round($perMinute),
			default              => '<1',
		};
	}
}
