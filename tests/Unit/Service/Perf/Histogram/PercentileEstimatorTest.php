<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Histogram;

use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Histogram\PercentileEstimator;
use PHPUnit\Framework\TestCase;

class PercentileEstimatorTest extends TestCase
{
	private PercentileEstimator $estimator;

	protected function setUp(): void
	{
		$this->estimator = new PercentileEstimator();
	}

	public function testAHistogramWithNoHitsHasNoPercentile(): void
	{
		$this->assertNull($this->estimator->estimateMs(HistogramBins::empty(), 0.95));
	}

	public function testThePercentileIsInterpolatedInsideTheBinItFallsIn(): void
	{
		// Ten hits, all between 250 ms and 500 ms: the median sits halfway across that bin.
		$histogram = $this->histogram([8 => 10]);

		$this->assertSame(375.0, $this->estimator->estimateMs($histogram, 0.5));
	}

	public function testThePercentileCannotLeaveTheBinItFallsIn(): void
	{
		$histogram = $this->histogram([7 => 10]);

		foreach ([0.0, 0.01, 0.5, 0.95, 0.99, 1.0] as $quantile) {
			$estimate = $this->estimator->estimateMs($histogram, $quantile);

			$this->assertGreaterThanOrEqual(HistogramBins::lowerEdgeMs(7), $estimate);
			$this->assertLessThanOrEqual(HistogramBins::upperEdgeMs(7), $estimate);
		}
	}

	public function testAHighPercentileIgnoresTheFastMajority(): void
	{
		// Half the requests are instant, half take between 1 s and 2 s.
		$histogram = $this->histogram([0 => 5, 10 => 5]);

		$this->assertSame(1900.0, $this->estimator->estimateMs($histogram, 0.95));
		$this->assertSame(1980.0, $this->estimator->estimateMs($histogram, 0.99));
	}

	public function testEmptyBinsAreSkippedRatherThanInterpolatedThrough(): void
	{
		$histogram = $this->histogram([2 => 4]);

		$this->assertSame(HistogramBins::lowerEdgeMs(2), $this->estimator->estimateMs($histogram, 0.0));
		$this->assertSame(HistogramBins::upperEdgeMs(2), $this->estimator->estimateMs($histogram, 1.0));
	}

	public function testTheOverflowBinEstimatesAtItsLowerEdge(): void
	{
		// Nothing bounds the last bin from above, so the only honest answer is "at least a minute".
		$histogram = $this->histogram([15 => 4]);

		$this->assertSame(60000.0, $this->estimator->estimateMs($histogram, 0.95));
	}

	public function testPercentilesNeverGoBackwards(): void
	{
		$histogram = $this->histogram([0 => 120, 4 => 300, 6 => 55, 9 => 12, 13 => 3]);

		$previous = 0.0;
		foreach (range(0, 100) as $percent) {
			$estimate = $this->estimator->estimateMs($histogram, $percent / 100);

			$this->assertGreaterThanOrEqual($previous, $estimate, "p{$percent}");
			$previous = $estimate;
		}
	}

	public function testTheNamedPercentilesAreTheOnesTheDashboardShows(): void
	{
		$histogram = $this->histogram([0 => 5, 10 => 5]);

		$this->assertSame($this->estimator->estimateMs($histogram, 0.5), $this->estimator->p50($histogram));
		$this->assertSame($this->estimator->estimateMs($histogram, 0.95), $this->estimator->p95($histogram));
		$this->assertSame($this->estimator->estimateMs($histogram, 0.99), $this->estimator->p99($histogram));
	}

	/**
	 * @dataProvider impossibleQuantiles
	 */
	public function testAQuantileOutsideZeroToOneIsAProgrammingError(float $quantile): void
	{
		$this->expectException(\InvalidArgumentException::class);

		$this->estimator->estimateMs($this->histogram([0 => 1]), $quantile);
	}

	/** @return array<string, array{float}> */
	public static function impossibleQuantiles(): array
	{
		return [
			'negative'     => [-0.1],
			'a percentage' => [95.0],
		];
	}

	public function testAMalformedHistogramIsRejected(): void
	{
		$this->expectException(\InvalidArgumentException::class);

		$this->estimator->p95([1, 2, 3]);
	}

	/**
	 * @param array<int, int> $counts
	 * @return list<int>
	 */
	private function histogram(array $counts): array
	{
		$histogram = HistogramBins::empty();
		foreach ($counts as $bin => $count) {
			$histogram[$bin] = $count;
		}

		return $histogram;
	}
}
