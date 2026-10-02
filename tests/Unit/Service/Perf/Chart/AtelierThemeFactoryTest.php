<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Chart;

use BugCatcher\Service\Perf\Chart\AtelierThemeFactory;
use PHPUnit\Framework\TestCase;

class AtelierThemeFactoryTest extends TestCase
{
	/**
	 * The whole point of the factory. A chart rendered on the server does not know which theme
	 * the browser is in, so every colour has to be a custom property that resolves in the page.
	 */
	public function testEveryColourIsADesignToken(): void
	{
		$theme = (new AtelierThemeFactory())->theme();

		$colours = [
			$theme->backgroundColor,
			$theme->textColor,
			$theme->mutedTextColor,
			$theme->gridColor,
			$theme->axisColor,
			...$theme->palette,
		];

		foreach ($colours as $colour) {
			$this->assertStringContainsString('var(--bc-', $colour);
		}
	}

	/**
	 * The chart sits inside a `.panel`, so the canvas it paints has to be the panel's own
	 * background - which also keeps the marker halos working, unlike `transparent`.
	 */
	public function testTheCanvasIsThePanelItSitsIn(): void
	{
		$this->assertSame('var(--bc-surface)', (new AtelierThemeFactory())->theme()->backgroundColor);
	}

	/** No webfont is shipped, so the chart reads in whatever the page is set in. */
	public function testTheTypefaceIsThePagesOwn(): void
	{
		$this->assertSame('inherit', (new AtelierThemeFactory())->theme()->fontFamily);
	}

	public function testTheMarkupCarriesClassesSoTheStylesheetCanReachIt(): void
	{
		$options = (new AtelierThemeFactory())->options();

		$this->assertTrue($options->classes);
		// data attributes nearly double the payload of an inline SVG and style nothing
		$this->assertFalse($options->dataAttributes);
	}

	/**
	 * The library's own formatter turns 1500 into "1.5k", which on a latency axis is wrong twice
	 * over: the unit is missing and the number is not a count.
	 *
	 * @dataProvider durations
	 */
	public function testAnAxisOfDurationsReadsAsDurations(float $value, string $expected): void
	{
		$this->assertSame($expected, (new AtelierThemeFactory())->milliseconds()->format($value));
	}

	/** @return array<string, array{float, string}> */
	public static function durations(): array
	{
		return [
			'nothing'      => [0.0, '0 ms'],
			'sub millisecond' => [0.4, '0.4 ms'],
			'milliseconds' => [210.0, '210 ms'],
			'a second'     => [1000.0, '1 s'],
			'seconds'      => [3100.0, '3.1 s'],
			'a minute'     => [62000.0, '62 s'],
		];
	}

	public function testAnAxisOfCountsStaysPlain(): void
	{
		$counts = (new AtelierThemeFactory())->counts();

		$this->assertSame('0', $counts->format(0.0));
		$this->assertSame('1200', $counts->format(1200.0));
		$this->assertSame('3', $counts->format(2.6));
	}

	public function testAnAxisOfBytesReadsAsBytes(): void
	{
		$this->assertSame('2 MiB', (new AtelierThemeFactory())->bytes()->format(2_097_152.0));
	}
}
