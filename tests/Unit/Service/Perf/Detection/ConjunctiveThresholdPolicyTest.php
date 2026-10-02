<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Detection;

use BugCatcher\Enum\PerfUnit;
use BugCatcher\Service\Perf\Detection\ConjunctiveThresholdPolicy;
use PHPUnit\Framework\TestCase;

class ConjunctiveThresholdPolicyTest extends TestCase
{
	public function testAThreefoldRiseOverTheFloorIsARegression(): void
	{
		$this->assertTrue($this->policy()->isAnomalous(210.0, 3100.0, 400, PerfUnit::Milliseconds));
	}

	/**
	 * The conditions are deliberately conjunctive. Without the absolute floor every route that
	 * does nothing would alert the moment it does slightly less nothing.
	 */
	public function testAThreefoldRiseThatIsStillFastIsNot(): void
	{
		$this->assertFalse($this->policy()->isAnomalous(5.0, 20.0, 400, PerfUnit::Milliseconds));
	}

	public function testARiseThatIsSlowButSmallIsNot(): void
	{
		$this->assertFalse($this->policy()->isAnomalous(800.0, 900.0, 400, PerfUnit::Milliseconds));
	}

	/** One slow request is not an incident. */
	public function testAWindowNobodyVisitedIsNot(): void
	{
		$this->assertFalse($this->policy()->isAnomalous(210.0, 3100.0, 19, PerfUnit::Milliseconds));
	}

	public function testGettingFasterIsNeverARegression(): void
	{
		$this->assertFalse($this->policy()->isAnomalous(3100.0, 210.0, 400, PerfUnit::Milliseconds));
		$this->assertFalse($this->policy()->isAnomalous(3100.0, 3100.0, 400, PerfUnit::Milliseconds));
	}

	/**
	 * `min_absolute_ms` is a duration, so it says nothing about a ratio or a byte count. For those
	 * the factor and the hit count carry the whole decision - and with twenty hits an error rate
	 * cannot be small enough to be noise.
	 */
	public function testTheAbsoluteFloorOnlyAppliesToDurations(): void
	{
		$this->assertTrue($this->policy()->isAnomalous(0.01, 0.1, 400, PerfUnit::Ratio));
		$this->assertTrue($this->policy()->isAnomalous(1_000_000.0, 8_000_000.0, 400, PerfUnit::Bytes));
	}

	/**
	 * A route that never errored and now does is a regression by any reading, and multiplying a
	 * baseline of zero by anything would say otherwise.
	 */
	public function testAnythingIsWorseThanNothing(): void
	{
		$this->assertTrue($this->policy()->isAnomalous(0.0, 0.1, 400, PerfUnit::Ratio));
	}

	public function testNothingIsNotWorseThanNothing(): void
	{
		$this->assertFalse($this->policy()->isAnomalous(0.0, 0.0, 400, PerfUnit::Ratio));
	}

	/** Even against a baseline of zero, a duration still has to be slow enough to matter. */
	public function testADurationAgainstNoBaselineStillHasToCrossTheFloor(): void
	{
		$this->assertFalse($this->policy()->isAnomalous(0.0, 50.0, 400, PerfUnit::Milliseconds));
		$this->assertTrue($this->policy()->isAnomalous(0.0, 500.0, 400, PerfUnit::Milliseconds));
	}

	private function policy(): ConjunctiveThresholdPolicy
	{
		return new ConjunctiveThresholdPolicy(factor: 3.0, minAbsoluteMs: 200, minHits: 20);
	}
}
