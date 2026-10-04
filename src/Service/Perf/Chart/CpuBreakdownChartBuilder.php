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

		// Nothing in this window reported CPU, which is what a window of Windows machines looks
		// like. Three bands of zero drawn under a heading that promises where the time went is
		// worse than no chart - the panel says so in words instead.
		if (!$this->anyCpu($series)) {
			return '';
		}

		$labels = $this->labels($series);

		$chart = Chart::stackedBar()
			->title($this->t('Where the time went'))
			->description($this->t('User CPU, system CPU and waiting, per request, per bucket.'))
			->size(self::WIDTH, self::HEIGHT)
			->vertical()
			->series(
				$this->t('user'),
				$this->points($labels, $series->series(static fn(PerfTimePoint $p): float => $p->userMs)),
				'var(--bc-perf-user)',
			)
			->series(
				$this->t('sys'),
				$this->points($labels, $series->series(static fn(PerfTimePoint $p): float => $p->sysMs)),
				'var(--bc-perf-sys)',
			)
			->series(
				$this->t('wait'),
				// a bucket from a machine with no CPU accounting contributes nothing to the band
				// rather than its whole duration - see PerfTimePoint::$waitMs
				$this->points($labels, $series->series(static fn(PerfTimePoint $p): float => $p->waitMs ?? 0.0)),
				'var(--bc-perf-wait)',
			)
			->build();

		return $this->finish($chart, $this->themes->milliseconds());
	}

	/** Whether any bucket in the window came from a machine that can measure CPU. */
	private function anyCpu(PerfTimeSeries $series): bool
	{
		foreach ($series->points as $point) {
			if ($point->hasCpu()) {
				return true;
			}
		}

		return false;
	}
}
