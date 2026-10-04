<?php

declare(strict_types=1);

namespace BugCatcher\Twig\Components;

use BugCatcher\Repository\RecordPerformanceRepository;
use DateTimeImmutable;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * How many routes `app:perf:detect` is currently shouting about.
 *
 * The other cells of the row are measurements; this one is a judgement the server already made,
 * against each route's own baseline on the same day of the week. That is the difference worth
 * having on the row: a p95 of 900 ms is alarming for a cached page and ordinary for a report,
 * and only the baseline knows which this is.
 *
 * Rows rather than occurrences - see {@see RecordPerformanceRepository::countOpenSince()} - and
 * only the unresolved ones, so clearing a record clears the cell.
 *
 * Opt-in: add `PerfRegressions` to `bug_catcher.perf_status_list_components`.
 */
#[AsTwigComponent]
final class PerfRegressions extends AbsComponent
{
	/** Wider than the other cells by default: a regression outlives the hour it was found in. */
	public int $hours = 24;

	public function __construct(private readonly RecordPerformanceRepository $records)
	{
	}

	public function getCount(): int
	{
		return $this->records->countOpenSince($this->project, new DateTimeImmutable("-{$this->hours} hours"));
	}
}
