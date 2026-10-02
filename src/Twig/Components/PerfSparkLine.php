<?php

declare(strict_types=1);

namespace BugCatcher\Twig\Components;

use Brendt\SparkLine\Period;
use Brendt\SparkLine\SparkLine;
use Brendt\SparkLine\SparkLineInterval;
use BugCatcher\Service\Perf\Report\Dto\PerfTimePoint;
use BugCatcher\Service\Perf\Report\PerfReportBuilder;
use DateTimeImmutable;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A day of p95 next to a project in the status list, the way {@see LogSparkLine} shows a day of
 * errors.
 *
 * It answers one question from across the room - is this getting slower - which is what the page
 * it lives on is for. Everything else about the route is a click away in `PerfOverview`.
 *
 * Opt-in: add `PerfSparkLine` to `bug_catcher.status_list_components`.
 */
#[AsTwigComponent]
final class PerfSparkLine extends AbsComponent
{
	public int $graphHours = 24;

	/** Drawing surface of the generated SVG. The rendered size is CSS's job - see the template. */
	private const int WIDTH = 250;

	private const int HEIGHT = 30;

	public function __construct(private readonly PerfReportBuilder $report)
	{
	}

	public function getSparkLine(): string
	{
		$sparkLine = SparkLine::new(collect($this->getSparkLineIntervals()), Period::HOUR)
			->withMaxItemAmount($this->graphHours)
			->withDimensions(self::WIDTH, self::HEIGHT)
			// CSS variables rather than hex: the SVG is inlined into the page, so the gradient
			// stops resolve against the active theme. See --bc-spark-* in app.css.
			->withColors('var(--bc-spark-low)', 'var(--bc-spark-mid)', 'var(--bc-spark-high)');

		return $this->stretchToContainer($sparkLine->make());
	}

	/**
	 * One point per hour that had traffic, carrying its p95 in whole milliseconds.
	 *
	 * The library counts events, so the "count" here is a latency - which is exactly what makes
	 * the shape mean "slower upwards". Hours with no traffic are left out rather than drawn as
	 * zero: a quiet night is not a fast night.
	 *
	 * @return list<SparkLineInterval>
	 */
	public function getSparkLineIntervals(): array
	{
		$series = $this->report->timeSeries(
			$this->project,
			new DateTimeImmutable("-{$this->graphHours} hours"),
			new DateTimeImmutable(),
		);

		$intervals = [];
		foreach ($series->points as $point) {
			if ($point->p95Ms !== null) {
				$intervals[] = new SparkLineInterval((int)round($point->p95Ms), $point->bucketAt);
			}
		}

		return $intervals;
	}

	/**
	 * The library writes a fixed width onto the `<svg>`, so the chart stays 250px wide inside a
	 * project card that is rarely 250px wide. Trading that width for a viewBox hands sizing to
	 * CSS; `preserveAspectRatio="none"` is what lets a sparkline stretch to fill rather than
	 * letterbox, which is the right trade here because the shape carries the trend, not a scale.
	 */
	private function stretchToContainer(string $svg): string
	{
		return preg_replace(
			'/^<svg width="(\d+)" height="(\d+)"/',
			'<svg viewBox="0 0 $1 $2" preserveAspectRatio="none" height="$2"',
			$svg,
			1,
		);
	}
}
