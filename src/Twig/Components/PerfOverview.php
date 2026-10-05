<?php

declare(strict_types=1);

namespace BugCatcher\Twig\Components;

use BugCatcher\Entity\Project;
use BugCatcher\Service\Perf\Chart\CpuBreakdownChartBuilder;
use BugCatcher\Service\Perf\Chart\LatencyBandsChartBuilder;
use BugCatcher\Service\Perf\Chart\StatusMixChartBuilder;
use BugCatcher\Service\Perf\Chart\ThroughputLatencyChartBuilder;
use BugCatcher\Service\Perf\Report\Dto\PerfTimeSeries;
use BugCatcher\Service\Perf\Report\PerfRangeResolver;
use BugCatcher\Service\Perf\Report\PerfReportBuilder;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The four charts of one project's window, server-rendered and without a line of JavaScript.
 *
 * It reads the window once and draws everything from it, because all four charts are the same
 * series asked four different questions: how much, how long, how the time was spent, and how it
 * ended.
 *
 * The window can be anchored to a day and a time rather than only ending now - see
 * {@see PerfRangeProps} - which is what makes a regression's link able to open the moment it was
 * found. The quick lengths stop at a week on purpose: beyond that the series is hundreds of buckets
 * and the page is megabytes of inline SVG for a shape nobody can read. `PerfRangeResolver::MAX_HOURS`
 * is the hard ceiling behind them.
 *
 * Opt-in: add `PerfOverview` to `bug_catcher.dashboard_components`.
 */
#[AsLiveComponent]
final class PerfOverview
{
	use DefaultActionTrait;
	use PerfRangeProps;

	#[LiveProp]
	public ?Project $project = null;

	private ?PerfTimeSeries $series = null;

	public function __construct(
		private readonly PerfReportBuilder $report,
		private readonly PerfRangeResolver $rangeResolver,
		private readonly ThroughputLatencyChartBuilder $throughput,
		private readonly LatencyBandsChartBuilder $bands,
		private readonly CpuBreakdownChartBuilder $cpu,
		private readonly StatusMixChartBuilder $status,
	) {
	}

	/**
	 * One read of the window, shared by every chart on the panel. Memoised because a template
	 * asks for the series and then for four charts drawn from it.
	 *
	 * With no project selected this is every project at once, which is what the dashboard shows
	 * by default and a fair question to ask of a server that watches several applications. With a
	 * `path` it is that one route - the fourth argument `timeSeries()` has always taken and nothing
	 * ever passed.
	 */
	public function getSeries(): PerfTimeSeries
	{
		$range = $this->getRange();

		return $this->series ??= $this->report->timeSeries(
			$this->project,
			$range->from,
			$range->to,
			$this->path,
		);
	}

	/**
	 * @return array{throughput: string, latency: string, bands: string, cpu: string, status: string}
	 *     each one SVG, or an empty string where there is nothing to draw
	 */
	public function getCharts(): array
	{
		$series = $this->getSeries();

		if ($series->isEmpty()) {
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

	protected function ranges(): PerfRangeResolver
	{
		return $this->rangeResolver;
	}
}
