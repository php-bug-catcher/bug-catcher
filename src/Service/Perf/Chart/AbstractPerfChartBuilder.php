<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Chart;

use Atelier\Chart\Chart;
use Atelier\Chart\Formatter\ValueFormatterInterface;
use Atelier\Chart\Model\ChartModel;
use BugCatcher\Service\Perf\Report\Dto\PerfTimeSeries;
use LogicException;

/**
 * What every chart on the performance page has in common.
 *
 * Three things the library leaves to the caller and all four charts need: turning a model into
 * markup that can be inlined into a page, thinning the x axis so a hundred labels do not overprint
 * into a smear, and refusing to build a chart out of nothing.
 */
abstract readonly class AbstractPerfChartBuilder
{
	/**
	 * The drawing surface, not the rendered size - the markup carries a `viewBox` and no
	 * dimensions, so the panel decides how wide it ends up. Wide enough that a legend fits on one
	 * row and the bars of a 168-bucket week are more than a hairline.
	 */
	protected const float WIDTH = 960.0;

	/** As many x labels as fit without touching. The rest of the ticks stay, only the text goes. */
	private const int MAX_LABELS = 12;

	public function __construct(protected AtelierThemeFactory $themes)
	{
	}

	/**
	 * Markup ready for `{{ svg|raw }}`: no XML declaration, no width or height, a `viewBox`, and
	 * the classes the stylesheet hooks onto.
	 */
	protected function finish(ChartModel $model, ?ValueFormatterInterface $formatter = null): string
	{
		$svg = Chart::renderDocument($model, $this->themes->theme(), $formatter, $this->themes->options())
			->makeResponsive()
			->setOmitXmlDeclaration(true)
			->toString();

		return $this->thinLabels($svg);
	}

	/**
	 * The x axis, one label per bucket.
	 *
	 * The library takes its categories from the array keys of a series, so two buckets with the
	 * same label would silently collapse into one - and with two series that then fails as
	 * "every series must use the same categories". The window widths
	 * {@see \BugCatcher\Service\Perf\Report\GranularityResolver} allows cannot produce a
	 * duplicate, so this is a guard against a future change of format rather than against data.
	 *
	 * @return list<string>
	 */
	protected function labels(PerfTimeSeries $series): array
	{
		$labels = $series->labels();

		if (count(array_unique($labels)) !== count($labels)) {
			throw new LogicException(
				'Two buckets of this window render the same label, which would collapse the chart.',
			);
		}

		return $labels;
	}

	/**
	 * @param list<string> $labels
	 * @param list<int|float> $values
	 * @return array<string, int|float>
	 */
	protected function points(array $labels, array $values): array
	{
		return array_combine($labels, $values);
	}

	/**
	 * Every category label is painted - there is no `labelStep` in the library - so at 120
	 * buckets the axis is an unreadable smear. Dropping the text of all but every nth leaves the
	 * grid and the bars untouched.
	 */
	private function thinLabels(string $svg): string
	{
		$total = preg_match_all('/class="atelier-chart__category-label"/', $svg);

		if ($total <= self::MAX_LABELS) {
			return $svg;
		}

		$every = (int)ceil($total / self::MAX_LABELS);
		$seen  = 0;

		// a closure rather than an arrow function: the counter has to survive between matches,
		// and `fn()` captures by value
		return (string)preg_replace_callback(
			'/<text[^>]*class="atelier-chart__category-label"[^>]*>.*?<\/text>/s',
			static function (array $match) use (&$seen, $every): string {
				return $seen++ % $every === 0 ? $match[0] : '';
			},
			$svg,
		);
	}
}
