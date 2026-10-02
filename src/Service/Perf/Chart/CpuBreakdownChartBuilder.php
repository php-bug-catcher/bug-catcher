<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Chart;

use Atelier\Chart\Chart;
use BugCatcher\Service\Perf\Report\Dto\PerfTimePoint;
use BugCatcher\Service\Perf\Report\Dto\PerfTimeSeries;

/**
 * What the average request of each bucket spent its time on: user CPU, system CPU, and waiting.
 *
 * The third band is the one worth having. Wallclock minus CPU is time spent on the database, on a
 * cache, on an HTTP call somebody added last week - usually the largest part of a slow request,
 * and the one no PHP profiler running on a production box will show you. phptop does not have it
 * at all.
 */
final readonly class CpuBreakdownChartBuilder extends AbstractPerfChartBuilder
{
	private const float HEIGHT = 280.0;

	public function build(PerfTimeSeries $series): string
	{
		if ($series->points === []) {
			return '';
		}

		$labels = $this->labels($series);

		$chart = Chart::stackedBar()
			->title('Where the time went')
			->description('User CPU, system CPU and waiting, per request, per bucket.')
			->size(self::WIDTH, self::HEIGHT)
			->vertical()
			->series(
				'user',
				$this->points($labels, $series->series(static fn(PerfTimePoint $p): float => $p->userMs)),
				'var(--bc-perf-user)',
			)
			->series(
				'sys',
				$this->points($labels, $series->series(static fn(PerfTimePoint $p): float => $p->sysMs)),
				'var(--bc-perf-sys)',
			)
			->series(
				'wait',
				$this->points($labels, $series->series(static fn(PerfTimePoint $p): float => $p->waitMs)),
				'var(--bc-perf-wait)',
			)
			->build();

		return $this->finish($chart, $this->themes->milliseconds());
	}
}
