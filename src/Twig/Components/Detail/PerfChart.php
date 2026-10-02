<?php

declare(strict_types=1);

namespace BugCatcher\Twig\Components\Detail;

use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Service\Perf\Chart\DetailChartBuilder;
use BugCatcher\Service\Perf\Report\Dto\PathDetailReport;
use BugCatcher\Service\Perf\Report\PerfReportBuilder;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The route of a regression, around the time it regressed, with what normal was drawn across it.
 *
 * The sentence on the record says what happened; this says what it looked like. Without the
 * reference line a spike invites "so what" - with it, the chart is the reason somebody was told.
 *
 * Registered for `RecordPerformance` in `bug_catcher.detail_components`.
 */
#[AsTwigComponent(template: '@BugCatcher/components/Detail/PerfChart.html.twig')]
final class PerfChart
{
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
}
