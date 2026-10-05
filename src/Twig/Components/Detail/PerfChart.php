<?php

declare(strict_types=1);

namespace BugCatcher\Twig\Components\Detail;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Service\Perf\Chart\DetailChartBuilder;
use BugCatcher\Service\Perf\Report\Dto\PathDetailReport;
use BugCatcher\Service\Perf\Report\Dto\PerfRange;
use BugCatcher\Service\Perf\Report\PerfReportBuilder;
use DateInterval;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The route of a regression, around the time it regressed, with what normal was drawn across it.
 *
 * The sentence on the record says what happened; this says what it looked like. Without the
 * reference line a spike invites "so what" - with it, the chart is the reason somebody was told.
 *
 * It is also the way out: {@see getAt()} and {@see getAnchor()} are what the link to `/performance`
 * is built from, so the one chart here can become the whole page, at the same moment, for the same
 * route.
 *
 * Registered for `RecordPerformance` in `bug_catcher.detail_components`.
 */
#[AsTwigComponent(template: '@BugCatcher/components/Detail/PerfChart.html.twig')]
final class PerfChart
{
	/**
	 * How long a window the link to `/performance` opens, in hours.
	 *
	 * One hour, so the page reads minute buckets - the resolution the spike is actually visible at,
	 * and the same choice {@see PerfReportBuilder::pathDetail()} makes for the chart here.
	 */
	public const int LINK_HOURS = 1;

	public Record $record;

	public ?PathDetailReport $report = null;

	public string $chart = '';

	public function __construct(
		private readonly PerfReportBuilder $builder,
		private readonly DetailChartBuilder $charts,
	) {
	}

	public function mount(Record $record): void
	{
		$this->record = $record;

		// the component is configured per record class, but a misconfiguration should leave the
		// rest of the detail page standing rather than take it down
		if (!$record instanceof RecordPerformance) {
			return;
		}

		$this->report = $this->builder->pathDetail($record);
		$this->chart  = $this->charts->build($this->report);
	}

	/**
	 * Where the linked window starts, as `at` spells it.
	 *
	 * Half a window before the regression, so the spike lands in the middle rather than against
	 * the left edge. The centring belongs here and not in
	 * {@see \BugCatcher\Service\Perf\Report\PerfRangeResolver}: `at` means the start of the window,
	 * so that a time somebody picked by hand is the time they get.
	 */
	public function getAt(): ?string
	{
		if (!$this->record instanceof RecordPerformance) {
			return null;
		}

		$at = $this->record->getWindowAt() ?? $this->record->getDate();

		return $at?->sub(new DateInterval(sprintf('PT%dM', (int)(self::LINK_HOURS * 60 / 2))))
			->format(PerfRange::AT_FORMAT);
	}

	public function getLinkHours(): int
	{
		return self::LINK_HOURS;
	}

	/**
	 * The fragment of the row to scroll to, matching {@see \BugCatcher\Service\Perf\Report\Dto\TopPathRow::anchor()}.
	 *
	 * Computed here because a Twig template cannot call a static method.
	 */
	public function getAnchor(): ?string
	{
		$path = $this->record instanceof RecordPerformance ? $this->record->getPath() : null;

		return $path === null ? null : PerfBucket::hashPath($path);
	}
}
