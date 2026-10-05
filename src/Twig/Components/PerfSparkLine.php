<?php

declare(strict_types=1);

namespace BugCatcher\Twig\Components;

use BugCatcher\Service\Perf\Report\Dto\PerfHealth;
use BugCatcher\Service\Perf\Report\Dto\PerfTimeSeries;
use BugCatcher\Service\Perf\Report\PerfReportBuilder;
use BugCatcher\Service\SparkLine\SparkLineGapMode;
use BugCatcher\Service\SparkLine\SparkLineRenderer;
use BugCatcher\Service\SparkLine\SparkLineScale;
use BugCatcher\Service\SparkLine\SparkLineSlots;
use DateTimeImmutable;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A day of p95 next to a project in the status list, the way {@see LogSparkLine} shows a day of
 * errors.
 *
 * It answers one question from across the room - is this getting slower - which is what the page
 * it lives on is for. Everything else about the route is a click away in `PerfOverview`.
 *
 * Opt-in: add `PerfSparkLine` to `bug_catcher.status_list_components`.
 */
#[AsTwigComponent]
final class PerfSparkLine extends AbsComponent
{
	public int $graphHours = 24;

	/**
	 * The latency the ramp runs out at, in milliseconds.
	 *
	 * Four times Apdex T - the edge of "frustrated", the same number `PerfApdex` scores against
	 * two cells to the left. It has to be a fixed budget and not the window's own maximum, which
	 * is what this used to do: scaling to the data means the tallest point of every window reaches
	 * the top of the chart, so the worst hour of a 20ms service is painted the same alarming red
	 * as the worst hour of a 2s one. "Slower than it was" is a question for `PerfOverview`; a cell
	 * on a wall monitor is being asked "is this slow".
	 */
	private const int SCALE_MAX_MS = 4 * PerfHealth::APDEX_THRESHOLD_MS;

	public function __construct(
		private readonly PerfReportBuilder $report,
		private readonly SparkLineSlots $slots,
		private readonly SparkLineRenderer $renderer,
	) {
	}

	public function getSparkLine(): string
	{
		return $this->renderer->render($this->getSlotValues(), new SparkLineScale(self::SCALE_MAX_MS));
	}

	/**
	 * The p95 of each hour in whole milliseconds, oldest first, one value per hour of the window.
	 *
	 * {@see PerfTimeSeries} already carries every bucket of the window, including the ones nothing
	 * happened in - so the slots are its points, in order. What this has to decide is what an hour
	 * with no traffic is worth, and the answer is the hour before it: latency is a gauge, not a
	 * counter, and a quiet night is not a fast night. Leaving those hours out, which is what this
	 * did before, handed the library a gap that it drew at the very bottom - an outage and an idle
	 * Sunday both rendered as "instant".
	 *
	 * @return list<float>
	 */
	public function getSlotValues(): array
	{
		$series = $this->report->timeSeries(
			$this->project,
			new DateTimeImmutable("-{$this->graphHours} hours"),
			new DateTimeImmutable(),
		);

		$bySlot = [];
		foreach ($series->points as $slot => $point) {
			if ($point->p95Ms !== null) {
				$bySlot[$slot] = round($point->p95Ms);
			}
		}

		return $this->slots->fill($bySlot, max(count($series->points), 1), SparkLineGapMode::Hold);
	}
}
