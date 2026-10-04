<?php

declare(strict_types=1);

namespace BugCatcher\Twig\Components;

use BugCatcher\Enum\PerfUnit;
use BugCatcher\Service\Perf\Report\Dto\PerfHealth;
use BugCatcher\Service\Perf\Report\ProjectHealthProvider;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * How long the slow tenth of requests took - the p95 of the window, beside the Apdex that says
 * how much of the traffic it is.
 *
 * The pair is deliberate. Apdex alone says a tenth of the users are unhappy and not how unhappy;
 * p95 alone says 4 s and not whether that is four requests or four thousand. Together they are
 * the two halves of one sentence, which is why neither is on the row without the other by
 * default.
 *
 * Printed through {@see PerfUnit} rather than as raw milliseconds, so a slow project reads
 * "1.4 s" and not "1402 ms": the row is read at a glance and four digits are not.
 *
 * Opt-in: in `bug_catcher.perf_status_list_components` by default.
 */
#[AsTwigComponent]
final class PerfLatency extends AbsComponent
{
	public int $hours = 1;

	public function __construct(private readonly ProjectHealthProvider $health)
	{
	}

	public function getHealth(): PerfHealth
	{
		return $this->health->forProject($this->project, $this->hours);
	}

	/** The p95 as a reader says it, or an en dash for a window nothing arrived in. */
	public function getFormatted(): string
	{
		$p95 = $this->getHealth()->p95Ms;

		return $p95 === null ? '–' : PerfUnit::Milliseconds->format($p95);
	}
}
