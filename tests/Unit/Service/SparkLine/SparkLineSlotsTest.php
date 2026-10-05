<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\SparkLine;

use BugCatcher\Service\SparkLine\SparkLineGapMode;
use BugCatcher\Service\SparkLine\SparkLineSlots;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Filling the buckets nothing was reported for. `brendt/php-sparkline` 2.x takes values and
 * nothing else, so a dropped bucket does not leave a hole in the chart - it shifts every point
 * after it and draws the line straight across whatever was missing.
 */
class SparkLineSlotsTest extends TestCase
{
	private SparkLineSlots $slots;

	protected function setUp(): void
	{
		$this->slots = new SparkLineSlots();
	}

	public function testTheLineHasExactlyAsManyPointsAsSlotsWereAskedFor(): void
	{
		$this->assertCount(96, $this->slots->fill([0 => 1, 95 => 2], 96, SparkLineGapMode::Zero));
		$this->assertCount(96, $this->slots->fill([], 96, SparkLineGapMode::Zero));
		$this->assertCount(1, $this->slots->fill([0 => 4], 1, SparkLineGapMode::Zero));
	}

	/** A counter: nobody logged an error, so there were no errors. */
	public function testAnEmptyBucketOfACounterIsZero(): void
	{
		$this->assertSame(
			[3.0, 0.0, 0.0, 7.0, 0.0],
			$this->slots->fill([0 => 3, 3 => 7], 5, SparkLineGapMode::Zero),
		);
	}

	/** A gauge: nobody measured, so the last measurement still stands. A quiet night is not fast. */
	public function testAnEmptyBucketOfAGaugeHoldsTheLastReading(): void
	{
		$this->assertSame(
			[120.0, 120.0, 120.0, 900.0, 900.0],
			$this->slots->fill([0 => 120, 3 => 900], 5, SparkLineGapMode::Hold),
		);
	}

	/** There is nothing to hold before the first reading, so a gauge opens on the baseline. */
	public function testAGaugeWithNothingToHoldYetStartsAtZero(): void
	{
		$this->assertSame(
			[0.0, 0.0, 400.0, 400.0],
			$this->slots->fill([2 => 400], 4, SparkLineGapMode::Hold),
		);
	}

	/** Values beyond the window are not points of it - the caller sized the line, not the data. */
	public function testASlotPastTheEndIsNotDrawn(): void
	{
		$this->assertSame([1.0, 2.0], $this->slots->fill([0 => 1, 1 => 2, 2 => 3, 9 => 4], 2, SparkLineGapMode::Zero));
	}

	public function testALineWithNoSlotsIsAProgrammingError(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->slots->fill([], 0, SparkLineGapMode::Zero);
	}
}
