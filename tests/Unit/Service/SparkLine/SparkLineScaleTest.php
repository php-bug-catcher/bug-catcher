<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\SparkLine;

use BugCatcher\Service\SparkLine\SparkLineScale;
use PHPUnit\Framework\TestCase;

/**
 * Where a value lands on the vertical, and - the whole reason this class exists - that it lands
 * on it at all. The library it feeds multiplies without clamping, so a value over the maximum
 * used to be given a coordinate outside the SVG.
 */
class SparkLineScaleTest extends TestCase
{
	/** @dataProvider values */
	public function testAValueIsProjectedOntoTheScale(float $value, float $max, int $expected): void
	{
		$this->assertSame($expected, (new SparkLineScale($max))->project($value));
	}

	public static function values(): iterable
	{
		yield 'the floor'               => [0.0, 5.0, 0];
		yield 'halfway'                 => [2.5, 5.0, 50];
		yield 'the maximum'             => [5.0, 5.0, SparkLineScale::STEPS];
		yield 'twice the maximum'       => [10.0, 5.0, SparkLineScale::STEPS];
		yield 'a hundred times over'    => [500.0, 5.0, SparkLineScale::STEPS];
		yield 'below the floor'         => [-7.0, 5.0, 0];
		yield 'latency under budget'    => [100.0, 2000.0, 5];
		yield 'latency over budget'     => [9000.0, 2000.0, SparkLineScale::STEPS];
	}

	/**
	 * Eight errors and eight hundred have to be told apart, which is the one thing the chart
	 * could not do before: both were drawn flat along the top edge, in the last colour of the ramp.
	 */
	public function testEverythingOverTheMaximumIsNotDrawnOutsideTheChart(): void
	{
		$scale = new SparkLineScale(5.0);

		foreach ([6.0, 40.0, 120.0, 1_000_000.0] as $value) {
			$this->assertLessThanOrEqual(SparkLineScale::STEPS, $scale->project($value));
		}
	}

	/**
	 * A threshold of zero has no "how far up" to answer. A flat baseline is the honest shape -
	 * coercing the maximum to one would send every non-zero value to the ceiling instead.
	 */
	public function testAThresholdOfZeroDrawsAFlatBaselineRatherThanDividingByIt(): void
	{
		$scale = new SparkLineScale(0.0);

		$this->assertSame(0, $scale->project(0.0));
		$this->assertSame(0, $scale->project(99.0));
	}
}
