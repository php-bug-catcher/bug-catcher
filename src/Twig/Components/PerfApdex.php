<?php

declare(strict_types=1);

namespace BugCatcher\Twig\Components;

use BugCatcher\Service\Perf\Report\Dto\PerfHealth;
use BugCatcher\Service\Perf\Report\ProjectHealthProvider;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * One number for "are the people using this waiting".
 *
 * Apdex is the industry's answer to the fact that no single latency reading says whether anybody
 * minded: a mean hides the tail, and a p95 says how slow the slow ones were but not how many of
 * them there are. The score counts a request satisfying up to 500 ms, tolerable up to 2 s, and
 * nothing beyond - so it falls when requests get slower *and* when more of them do, which is
 * what a glance across an office needs.
 *
 * The number is the glance and the colour is the alarm; the four words of the index's own rating
 * are in the tooltip, because 0.91 means nothing to somebody seeing it for the first time.
 *
 * Opt-in: in `bug_catcher.perf_status_list_components` by default.
 */
#[AsTwigComponent]
final class PerfApdex extends AbsComponent
{
	/** The window the row reads. An hour, so it is read from minute buckets and needs no roll-up. */
	public int $hours = 1;

	public function __construct(private readonly ProjectHealthProvider $health)
	{
	}

	public function getHealth(): PerfHealth
	{
		return $this->health->forProject($this->project, $this->hours);
	}

	/**
	 * Apdex's own bands, not ours - 0.94 excellent, 0.85 good, 0.70 fair. The dashboard's job is
	 * to make the handful that need attention stand out, so "good" is as quiet as "excellent"
	 * and only "fair" starts to shout.
	 */
	public function getTone(): string
	{
		return match ($this->getHealth()->apdexRating()) {
			'excellent', 'good' => 'ok',
			'fair'              => 'warn',
			null                => 'idle',
			default             => 'danger',
		};
	}
}
