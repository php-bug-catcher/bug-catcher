<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Chart;

use Atelier\Chart\Formatter\ValueFormatterInterface;
use Atelier\Chart\Renderer\Svg\SvgRenderOptions;
use Atelier\Chart\Theme\Theme;
use BugCatcher\Enum\PerfUnit;

/**
 * The bridge between `atelier/chart` and the `--bc-*` design tokens.
 *
 * A chart is rendered on the server, which does not know - and must not need to know - which
 * theme the browser is in. So no colour here is a colour: every one of them is a custom property
 * that resolves in the page, and flipping `data-theme` recolours charts that were rendered
 * minutes ago without a single byte going over the wire. Resolving the tokens to hex server-side
 * would be the one way to break the theme switch.
 *
 * The library does not validate colours, which is what makes this work: `var(--bc-accent)` is
 * written into the `fill` attribute verbatim. Classes are switched on as well, so the stylesheet
 * can reach the parts PHP cannot configure - the per-point markers of a line, the baked-in title.
 */
final readonly class AtelierThemeFactory
{
	public function theme(): Theme
	{
		return new Theme(
			// the panel's own background rather than `transparent`: the same colour is reused as
			// the halo of line markers and the text of in-bar labels, and a transparent halo
			// lets the line show through the marker
			backgroundColor: 'var(--bc-surface)',
			textColor: 'var(--bc-fg)',
			mutedTextColor: 'var(--bc-muted)',
			gridColor: 'var(--bc-grid)',
			axisColor: 'var(--bc-line)',
			// the fallback order for series that are not given a colour of their own
			palette: [
				'var(--bc-perf-avg)',
				'var(--bc-perf-p95)',
				'var(--bc-perf-wait)',
				'var(--bc-perf-band-1)',
				'var(--bc-perf-band-5)',
			],
			// no webfont is shipped, so the chart reads in whatever the page is set in
			fontFamily: 'inherit',
		);
	}

	public function options(): SvgRenderOptions
	{
		// data attributes nearly double the payload of an inline SVG and style nothing
		return new SvgRenderOptions(classes: true, dataAttributes: false);
	}

	/** Tick labels for an axis of durations: 210 ms, 3.1 s. */
	public function milliseconds(): ValueFormatterInterface
	{
		return $this->formatter(PerfUnit::Milliseconds);
	}

	/** Tick labels for an axis of whatever unit the chart is drawn in: 2 MiB, 12%, 3.1 s. */
	public function forUnit(PerfUnit $unit): ValueFormatterInterface
	{
		return $this->formatter($unit);
	}

	/**
	 * Tick labels for an axis of requests. The library's own formatter would render 1200 as
	 * "1.2k", which hides exactly the difference a traffic chart is read for.
	 */
	public function counts(): ValueFormatterInterface
	{
		return new class implements ValueFormatterInterface {
			public function format(float $value): string
			{
				return (string)(int)round($value);
			}
		};
	}

	/**
	 * Axis labels say what the numbers mean, in the same words the rest of the module uses -
	 * {@see PerfUnit::format()} is what a notification and a table already print.
	 */
	private function formatter(PerfUnit $unit): ValueFormatterInterface
	{
		return new class ($unit) implements ValueFormatterInterface {
			public function __construct(private readonly PerfUnit $unit)
			{
			}

			public function format(float $value): string
			{
				return $this->unit->format($value);
			}
		};
	}
}
