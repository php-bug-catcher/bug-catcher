<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Chart;

use Atelier\Chart\Chart;
use BugCatcher\Service\Perf\Report\Dto\PerfTimePoint;
use BugCatcher\Service\Perf\Report\Dto\PerfTimeSeries;
use BugCatcher\Service\Perf\SqlMetrics;

/**
 * What the average request asked the database for: how many queries, and how long they took.
 *
 * The pair answers the question the "where the time went" chart raises and cannot settle. That
 * chart shows waiting as one band - the database, a cache, an HTTP call, all together - and this
 * splits the database out of it by name, because the application counted it rather than the
 * server inferring it.
 *
 * Two charts for the same reason {@see ThroughputLatencyChartBuilder} draws two: a count and a
 * duration cannot share a linear scale, and the library has one scale per model. Reading them
 * stacked is the point - queries flat while the time rises is a slow database, both rising
 * together is a query somebody added to a loop.
 */
final readonly class DatabaseChartBuilder extends AbstractPerfChartBuilder
{
	private const float QUERIES_HEIGHT = 200.0;

	private const float TIME_HEIGHT = 240.0;

	/** @return array{queries: string, time: string} two charts of one width, or empty strings */
	public function build(PerfTimeSeries $series): array
	{
		if ($series->points === []) {
			return ['queries' => '', 'time' => ''];
		}

		$labels = $this->labels($series);

		$queries = Chart::bar()
			->title($this->t('Queries per request'))
			->description($this->t('Database queries the average request of each bucket ran.'))
			->size(self::WIDTH, self::QUERIES_HEIGHT)
			->showValues(false)
			->series(
				$this->t('queries'),
				$this->points($labels, $series->series(
					// a bucket the application did not instrument is a zero here rather than a
					// gap: the library has no notion of a missing point on a bar, and the panel
					// has already refused to draw anything at all if no bucket reported
					static fn(PerfTimePoint $point): float => $point->extraPerHit(SqlMetrics::QUERIES) ?? 0.0,
				)),
				'var(--bc-perf-queries)',
			)
			->build();

		$time = Chart::line()
			->title($this->t('Time in the database'))
			->description($this->t('Milliseconds the average request of each bucket spent waiting on the database.'))
			->size(self::WIDTH, self::TIME_HEIGHT)
			->includeZero(true)
			->series(
				$this->t('in the database'),
				$this->points($labels, $series->series(
					static fn(PerfTimePoint $point): float
						=> ($point->extraPerHit(SqlMetrics::SECONDS) ?? 0.0) * 1000,
				)),
				'var(--bc-perf-dbtime)',
			)
			// the band it is a part of, so the chart says how much of the waiting it explains
			->series(
				$this->t('all waiting'),
				$this->points($labels, $series->series(static fn(PerfTimePoint $point): float => $point->waitMs)),
				'var(--bc-perf-wait)',
			)
			->build();

		return [
			'queries' => $this->finish($queries, $this->themes->counts()),
			'time'    => $this->finish($time, $this->themes->milliseconds()),
		];
	}
}
