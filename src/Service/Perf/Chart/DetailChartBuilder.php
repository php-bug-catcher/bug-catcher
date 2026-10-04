<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Chart;

use Atelier\Chart\Chart;
use Atelier\Chart\Layout\PlotLayout;
use Atelier\Chart\Model\CartesianChart;
use Atelier\Chart\Scale\LinearScale;
use Atelier\Svg\Document;
use Atelier\Svg\Element\Shape\LineElement;
use BugCatcher\Enum\PerfMetric;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Service\Perf\Report\Dto\PathDetailReport;
use BugCatcher\Service\Perf\Report\Dto\PerfTimePoint;

/**
 * The chart on the detail page of a regression: the route around the time it was found, with
 * what normal looked like drawn across it.
 *
 * The reference line is the point of the whole component. A chart that only shows a spike invites
 * "so what"; a chart with the baseline on it says why somebody was told, in the same picture.
 * The library has no annotation API, so the line is placed by reproducing the layout - which is
 * exact, because both the plot box and the scale are public and deterministic.
 */
final readonly class DetailChartBuilder extends AbstractPerfChartBuilder
{
	private const float HEIGHT = 300.0;

	/** One series, so the renderer reserves no legend row and the plot box is predictable. */
	private const int LEGEND_ROWS = 0;

	public function build(PathDetailReport $report): string
	{
		// a flat line of zeros for a route whose measurements are past their retention would
		// read as "it was idle", which is a different and wrong statement
		if ($report->series->isEmpty()) {
			return '';
		}

		$metric = PerfMetric::tryFrom($report->metric);
		$values = $this->valuesOf($report, $metric);

		if ($values === null) {
			return '';
		}

		$labels = $this->labels($report->series);
		$unit   = $metric?->unit() ?? $report->unit;

		$model = Chart::line()
			->title($this->t('%metric% on %path%', [
				'%metric%' => $metric === null ? $report->metric : $this->t($metric->label()),
				'%path%'   => $report->path,
			]))
			->description($this->t('%metric% of %path% around the window it regressed in.', [
				'%metric%' => $report->metric,
				'%path%'   => $report->path,
			]))
			->size(self::WIDTH, self::HEIGHT)
			->includeZero(true)
			->series($this->t('observed'), $this->points($labels, $values), 'var(--bc-perf-p95)')
			->build();

		$document = Chart::renderDocument(
			$model,
			$this->themes->theme(),
			$this->themes->forUnit($unit),
			$this->themes->options(),
		);

		if ($report->baseline !== null) {
			$this->drawBaseline($document, $model, $report->baseline);
		}

		return $this->finishDocument($document);
	}

	/**
	 * The series the record was written from, rebuilt out of the buckets.
	 *
	 * A metric an application registered under `perf.metrics` cannot be rebuilt here - the time
	 * points hold what the bundle knows how to compute - so rather than draw a different number
	 * under its name, the component says nothing and the detail page keeps the sentence and the
	 * history list.
	 *
	 * @return list<float>|null
	 */
	private function valuesOf(PathDetailReport $report, ?PerfMetric $metric): ?array
	{
		$of = match ($metric) {
			PerfMetric::P95       => static fn(PerfTimePoint $point): float => $point->p95Ms ?? 0.0,
			PerfMetric::Avg       => static fn(PerfTimePoint $point): float => $point->avgMs,
			PerfMetric::ErrorRate => static fn(PerfTimePoint $point): float => $point->errorRate(),
			PerfMetric::Mem       => static fn(PerfTimePoint $point): float => $point->memPerHit,
			null                  => null,
		};

		return $of === null ? null : $report->series->series($of);
	}

	/**
	 * `PlotLayout` and `LinearScale` are what the renderer itself used, so asking them again puts
	 * the line exactly on the value it names - the same place the gridline for that number is.
	 */
	private function drawBaseline(Document $document, CartesianChart $model, float $baseline): void
	{
		$frame = (new PlotLayout())->cartesian(self::WIDTH, self::HEIGHT, self::LEGEND_ROWS);
		$scale = LinearScale::forValues(
			$model->values(),
			$frame->plot->bottom(),
			$frame->plot->y,
			domain: $model->domain,
		);

		$document->getRoot()->appendChild(
			(new LineElement())
				->setAttribute('x1', round($frame->plot->x, 2))
				->setAttribute('y1', round($scale->map($baseline), 2))
				->setAttribute('x2', round($frame->plot->right(), 2))
				->setAttribute('y2', round($scale->map($baseline), 2))
				->setAttribute('stroke', 'var(--bc-perf-baseline)')
				->setAttribute('stroke-width', '1.5')
				->setAttribute('stroke-dasharray', '6 4')
				->setAttribute('class', 'bc-perf-baseline'),
		);
	}
}
