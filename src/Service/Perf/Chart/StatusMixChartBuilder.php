<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Chart;

use Atelier\Chart\Chart;
use BugCatcher\Service\Perf\Report\Dto\PerfTimePoint;
use BugCatcher\Service\Perf\Report\Dto\PerfTimeSeries;

/**
 * How each bucket answered: fine, client error, server error.
 *
 * Three bands and not the four the design draws. The collector counts client and server errors
 * and nothing else, so a redirect is indistinguishable from a success on the wire - a fifth
 * counter in every bucket of every minute would be a lot of storage for one line.
 */
final readonly class StatusMixChartBuilder extends AbstractPerfChartBuilder
{
	private const float HEIGHT = 240.0;

	private const array COLOURS = [
		'2xx / 3xx' => 'var(--bc-perf-ok)',
		'4xx'       => 'var(--bc-perf-4xx)',
		'5xx'       => 'var(--bc-perf-5xx)',
	];

	public function build(PerfTimeSeries $series): string
	{
		if ($series->points === []) {
			return '';
		}

		$labels  = $this->labels($series);
		$builder = Chart::stackedBar()
			->title($this->t('Status mix'))
			->description($this->t('Requests per status class, per bucket.'))
			->size(self::WIDTH, self::HEIGHT)
			->vertical();

		foreach (self::COLOURS as $name => $colour) {
			$builder->series(
				$name,
				$this->points($labels, $series->series(
					static fn(PerfTimePoint $point): int => $point->statusMix()[$name],
				)),
				$colour,
			);
		}

		return $this->finish($builder->build(), $this->themes->counts());
	}
}
