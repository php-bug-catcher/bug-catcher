<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\SparkLine;

use BugCatcher\Service\SparkLine\SparkLineRenderer;
use BugCatcher\Service\SparkLine\SparkLineScale;
use PHPUnit\Framework\TestCase;

/**
 * The SVG itself: the three things `brendt/php-sparkline` gets wrong for a cell on a wall monitor,
 * pinned so they cannot come back.
 */
class SparkLineRendererTest extends TestCase
{
	private SparkLineRenderer $renderer;

	protected function setUp(): void
	{
		$this->renderer = new SparkLineRenderer();
	}

	/**
	 * The drawing surface is 30px tall and the library draws into the top 25 of it. A value over
	 * the scale's maximum used to be multiplied straight past that - 120 errors against a
	 * threshold of 5 came out at y=600 - so the browser clipped the line flat along the top edge,
	 * inside the red band. Every busy project drew the same picture.
	 */
	public function testNoPointIsDrawnOutsideTheChart(): void
	{
		$points = $this->points($this->renderer->render([3.0, 8.0, 40.0, 120.0, 2.0], new SparkLineScale(5.0)));

		foreach ($points as [, $y]) {
			$this->assertGreaterThanOrEqual(0, $y);
			$this->assertLessThanOrEqual(25, $y);
		}
	}

	/** Over the top of the scale is the top of the chart, not above it. */
	public function testTheTopOfTheScaleIsTheTopOfTheChart(): void
	{
		$points = $this->points($this->renderer->render([0.0, 5.0, 500.0], new SparkLineScale(5.0)));

		$this->assertSame(0, $points[0][1]);
		$this->assertSame(25, $points[1][1]);
		$this->assertSame(25, $points[2][1]);
	}

	/**
	 * The library's x step is `floor($width / $slots)`, which at the old fixed 250px over 96 slots
	 * put the last point at x=192 and left the right quarter of the cell blank. Sizing the canvas
	 * as a whole number of slots makes the step exact instead.
	 */
	public function testTheLineSpansTheWholeWidth(): void
	{
		$svg    = $this->renderer->render(array_fill(0, 96, 1.0), new SparkLineScale(5.0));
		$points = $this->points($svg);

		$this->assertCount(96, $points);
		$this->assertSame(0, $points[0][0]);
		$this->assertSame($this->viewBoxWidth($svg), end($points)[0]);
	}

	/** Sizing is the stylesheet's - see `.sparkline svg` in app.css. */
	public function testTheSizeIsLeftToTheStylesheet(): void
	{
		$svg = $this->renderer->render([1.0, 2.0], new SparkLineScale(5.0));

		$this->assertStringStartsWith('<svg viewBox="0 0 ', $svg);
		$this->assertStringContainsString('preserveAspectRatio="none"', $svg);
	}

	/** Inlined into the page, so the gradient stops resolve against the theme in the browser. */
	public function testTheColoursAreThemeTokens(): void
	{
		$svg = $this->renderer->render([1.0, 4.0], new SparkLineScale(5.0));

		$this->assertStringContainsString('var(--bc-spark-low)', $svg);
		$this->assertStringContainsString('var(--bc-spark-mid)', $svg);
		$this->assertStringContainsString('var(--bc-spark-high)', $svg);
		$this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{6}/', $svg);
	}

	/**
	 * A window can hold one bucket or none, and a polyline needs two points. The single value
	 * stays at its own height rather than being flattened onto the baseline.
	 *
	 * @dataProvider sparseWindows
	 */
	public function testAWindowWithAlmostNothingInItStillDrawsAnSvg(array $values, array $expectedY): void
	{
		$points = $this->points($this->renderer->render($values, new SparkLineScale(5.0)));

		$this->assertSame($expectedY, array_column($points, 1));
	}

	public static function sparseWindows(): iterable
	{
		yield 'nothing'      => [[], [0, 0]];
		yield 'one quiet'    => [[0.0], [0, 0]];
		yield 'one at the top' => [[5.0], [25, 25]];
	}

	/** @return list<array{int, int}> the polyline's points, x and y, y counted up from the floor */
	private function points(string $svg): array
	{
		$this->assertMatchesRegularExpression('/points="[^"]+"/', $svg);
		preg_match('/points="([^"]+)"/', $svg, $matches);

		return array_map(
			static fn(string $pair): array => array_map('intval', explode(',', $pair)),
			explode(' ', trim($matches[1])),
		);
	}

	private function viewBoxWidth(string $svg): int
	{
		preg_match('/viewBox="0 0 (\d+) /', $svg, $matches);

		return (int)$matches[1];
	}
}
