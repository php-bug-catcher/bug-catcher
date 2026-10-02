<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Chart;

use Atelier\Chart\Chart;
use BugCatcher\Service\Perf\Report\Dto\PerfTimePoint;
use BugCatcher\Service\Perf\Report\Dto\PerfTimeSeries;

/**
 * Traffic and latency over the window - how much came in, and how long it took.
 *
 * Two charts rather than one with two axes, because the library has one linear scale per model.
 * That is not the compromise it sounds like: the plot box is laid out from fixed insets, so two
 * charts of the same width line up to the pixel, and reading a bar chart above a line chart is no
 * harder than reading a dual axis - arguably easier, since nobody has to work out which axis a
 * line belongs to.
 */
final readonly class ThroughputLatencyChartBuilder extends AbstractPerfChartBuilder
{
	private const float HITS_HEIGHT = 200.0;

	private const float LATENCY_HEIGHT = 280.0;

	/** @return array{hits: string, latency: string} two charts of one width, or empty strings */
	public function build(PerfTimeSeries $series): array
	{
		if ($series->points === []) {
			return ['hits' => '', 'latency' => ''];
		}

		$labels = $this->labels($series);

		$hits = Chart::bar()
			->title('Requests')
			->description('Requests per bucket.')
			->size(self::WIDTH, self::HITS_HEIGHT)
			// a number above every bar is unreadable past a handful of buckets
			->showValues(false)
			->series(
				'hits',
				$this->points($labels, $series->series(static fn(PerfTimePoint $point): int => $point->hits)),
				'var(--bc-perf-hits)',
			)
			->build();

		$latency = Chart::line()
			->title('Latency')
			->description('Mean and 95th percentile response time per bucket.')
			->size(self::WIDTH, self::LATENCY_HEIGHT)
			// a latency axis that does not start at zero exaggerates every wobble
			->includeZero(true)
			->series(
				'mean',
				$this->points($labels, $series->series(static fn(PerfTimePoint $point): float => $point->avgMs)),
				'var(--bc-perf-avg)',
			)
			->series(
				'p95',
				// a bucket nobody visited has no percentile; on a line it is a zero
				$this->points($labels, $series->series(static fn(PerfTimePoint $point): float => $point->p95Ms ?? 0.0)),
				'var(--bc-perf-p95)',
			)
			->build();

		return [
			'hits'    => $this->finish($hits, $this->themes->counts()),
			'latency' => $this->finish($latency, $this->themes->milliseconds()),
		];
	}
}
