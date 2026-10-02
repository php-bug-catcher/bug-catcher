<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Histogram;

use BugCatcher\Service\Perf\Histogram\HistogramBins;
use PHPUnit\Framework\TestCase;

class HistogramBinsTest extends TestCase
{
	/**
	 * The wire format. These edges are also
	 * `BugCatcher\PerfCollector\Histogram\DurationHistogram::EDGES_MS` in the collector package,
	 * which shares no code with this one. Changing either side is a migration of stored data, and
	 * this literal is what makes that impossible to do by accident.
	 */
	public function testTheEdgesAreTheOnesTheCollectorShips(): void
	{
		$this->assertSame(
			[1, 2, 5, 10, 25, 50, 100, 250, 500, 1000, 2000, 5000, 10000, 30000, 60000],
			HistogramBins::EDGES_MS,
		);
	}

	public function testThereIsOneMoreBinThanEdges(): void
	{
		$this->assertSame(count(HistogramBins::EDGES_MS) + 1, HistogramBins::COUNT);
		$this->assertSame(16, HistogramBins::COUNT);
	}

	public function testAnEmptyHistogramIsAZeroPerBin(): void
	{
		$empty = HistogramBins::empty();

		$this->assertCount(HistogramBins::COUNT, $empty);
		$this->assertSame([0], array_unique($empty));
		$this->assertSame(range(0, HistogramBins::COUNT - 1), array_keys($empty));
	}

	public function testBinsAreHalfOpenAndLowerInclusive(): void
	{
		$this->assertSame(0.0, HistogramBins::lowerEdgeMs(0));
		$this->assertSame(1.0, HistogramBins::upperEdgeMs(0));

		$this->assertSame(1.0, HistogramBins::lowerEdgeMs(1));
		$this->assertSame(2.0, HistogramBins::upperEdgeMs(1));

		$this->assertSame(100.0, HistogramBins::lowerEdgeMs(7));
		$this->assertSame(250.0, HistogramBins::upperEdgeMs(7));
	}

	public function testEveryBinsUpperEdgeIsTheNextBinsLowerEdge(): void
	{
		for ($bin = 0; $bin < HistogramBins::COUNT - 1; $bin++) {
			$this->assertSame(
				HistogramBins::upperEdgeMs($bin),
				HistogramBins::lowerEdgeMs($bin + 1),
				"bin {$bin}",
			);
		}
	}

	public function testTheLastBinHasNoUpperEdge(): void
	{
		$last = HistogramBins::COUNT - 1;

		$this->assertSame(60000.0, HistogramBins::lowerEdgeMs($last));
		$this->assertNull(HistogramBins::upperEdgeMs($last));
	}

	/**
	 * @dataProvider outOfRangeBins
	 */
	public function testABinOutsideTheScaleIsAProgrammingError(int $bin): void
	{
		$this->expectException(\InvalidArgumentException::class);

		HistogramBins::lowerEdgeMs($bin);
	}

	/** @return array<string, array{int}> */
	public static function outOfRangeBins(): array
	{
		return [
			'negative'  => [-1],
			'past the end' => [HistogramBins::COUNT],
		];
	}

	/**
	 * @dataProvider durations
	 */
	public function testBinForIsTheSameBinTheCollectorWouldHaveRecordedIn(float $ms, int $expected): void
	{
		$this->assertSame($expected, HistogramBins::binFor($ms));
	}

	/** @return array<string, array{float, int}> */
	public static function durations(): array
	{
		return [
			'instant'            => [0.0, 0],
			'under a ms'         => [0.9, 0],
			'exactly an edge'    => [1.0, 1],
			'just under an edge' => [0.999, 0],
			'a second'           => [1000.0, 10],
			'half a second'      => [500.0, 9],
			'a minute'           => [60000.0, 15],
			'beyond the scale'   => [3600000.0, 15],
			'negative nonsense'  => [-5.0, 0],
		];
	}
}
