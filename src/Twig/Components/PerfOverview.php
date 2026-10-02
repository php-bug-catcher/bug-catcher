<?php

declare(strict_types=1);

namespace BugCatcher\Twig\Components;

use BugCatcher\Entity\Project;
use BugCatcher\Service\Perf\Chart\CpuBreakdownChartBuilder;
use BugCatcher\Service\Perf\Chart\LatencyBandsChartBuilder;
use BugCatcher\Service\Perf\Chart\StatusMixChartBuilder;
use BugCatcher\Service\Perf\Chart\ThroughputLatencyChartBuilder;
use BugCatcher\Service\Perf\Report\Dto\PerfTimeSeries;
use BugCatcher\Service\Perf\Report\PerfReportBuilder;
use DateTimeImmutable;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The four charts of one project's last window, server-rendered and without a line of JavaScript.
 *
 * It reads the window once and draws everything from it, because all four charts are the same
 * series asked four different questions: how much, how long, how the time was spent, and how it
 * ended.
 *
 * The window is the only control. The choices stop at a week on purpose - beyond that the series
 * is hundreds of buckets and the page would be megabytes of inline SVG for a shape nobody can
 * read anyway.
 *
 * Opt-in: add `PerfOverview` to `bug_catcher.dashboard_components`.
 */
#[AsLiveComponent]
final class PerfOverview
{
	use DefaultActionTrait;

	#[LiveProp]
	public ?Project $project = null;

	#[LiveProp(writable: true)]
	public int $hours = 1;

	private ?PerfTimeSeries $series = null;

	public function __construct(
		private readonly PerfReportBuilder $report,
		private readonly ThroughputLatencyChartBuilder $throughput,
		private readonly LatencyBandsChartBuilder $bands,
		private readonly CpuBreakdownChartBuilder $cpu,
		private readonly StatusMixChartBuilder $status,
	) {
	}

	/**
	 * One read of the window, shared by every chart on the panel. Memoised because a template
	 * asks for the series and then for four charts drawn from it.
	 */
	public function getSeries(): ?PerfTimeSeries
	{
		if ($this->project === null) {
			return null;
		}

		return $this->series ??= $this->report->timeSeries(
			$this->project,
			new DateTimeImmutable("-{$this->hours} hours"),
			new DateTimeImmutable(),
		);
	}

	/**
	 * @return array{throughput: string, latency: string, bands: string, cpu: string, status: string}
	 *     each one SVG, or an empty string where there is nothing to draw
	 */
	public function getCharts(): array
	{
		$series = $this->getSeries();

		if ($series === null || $series->isEmpty()) {
			return ['throughput' => '', 'latency' => '', 'bands' => '', 'cpu' => '', 'status' => ''];
		}

		$throughput = $this->throughput->build($series);

		return [
			'throughput' => $throughput['hits'],
			'latency'    => $throughput['latency'],
			'bands'      => $this->bands->build($series),
			'cpu'        => $this->cpu->build($series),
			'status'     => $this->status->build($series),
		];
	}

	/** @return array<int, string> the windows the control offers, in hours */
	public function getWindows(): array
	{
		return [1 => '1 h', 6 => '6 h', 24 => '24 h', 168 => '7 d'];
	}
}
