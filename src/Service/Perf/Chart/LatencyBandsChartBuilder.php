<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Chart;

use Atelier\Chart\Chart;
use BugCatcher\Service\Perf\Report\Dto\PerfTimePoint;
use BugCatcher\Service\Perf\Report\Dto\PerfTimeSeries;

/**
 * How the requests of each bucket were spread over five bands of "how long did you wait".
 *
 * The chart the histogram exists for. A mean of 300 ms says nothing about whether every request
 * took 300 ms or nine took fifty and one took three seconds, and it is the second case somebody
 * is complaining about. Stacked, the slow band growing is visible long before the mean moves.
 *
 * Stacked bars rather than the stacked area the design asks for: the library's area chart
 * overlays its series from the baseline at 14 % opacity instead of stacking them. At the
 * densities this page draws, columns read the same and the colours are the legend's colours.
 */
final readonly class LatencyBandsChartBuilder extends AbstractPerfChartBuilder
{
	private const float HEIGHT = 280.0;

	/** Fastest first, so the stack grows upwards into the colours that mean trouble. */
	private const array COLOURS = [
		'var(--bc-perf-band-1)',
		'var(--bc-perf-band-2)',
		'var(--bc-perf-band-3)',
		'var(--bc-perf-band-4)',
		'var(--bc-perf-band-5)',
	];

	public function build(PerfTimeSeries $series): string
	{
		if ($series->points === []) {
			return '';
		}

		$labels  = $this->labels($series);
		$builder = Chart::stackedBar()
			->title($this->t('Latency bands'))
			->description($this->t('Share of requests per duration band.'))
			->size(self::WIDTH, self::HEIGHT)
			->vertical();

		$band = 0;
		foreach (array_keys($series->points[0]->bands->toArray()) as $name) {
			$builder->series(
				$name,
				$this->points($labels, $series->series(
					static fn(PerfTimePoint $point): int => $point->bands->toArray()[$name],
				)),
				self::COLOURS[$band++],
			);
		}

		return $this->finish($builder->build(), $this->themes->counts());
	}
}
