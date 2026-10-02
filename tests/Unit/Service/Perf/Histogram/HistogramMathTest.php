<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Histogram;

use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Histogram\HistogramMath;
use PHPUnit\Framework\TestCase;

class HistogramMathTest extends TestCase
{
	public function testAddingIsElementWise(): void
	{
		$a = $this->histogram([0 => 1, 5 => 2]);
		$b = $this->histogram([5 => 3, 15 => 7]);

		$this->assertSame(
			$this->histogram([0 => 1, 5 => 5, 15 => 7]),
			HistogramMath::add($a, $b),
		);
	}

	public function testAddingDoesNotTouchTheOperands(): void
	{
		$a = $this->histogram([1 => 1]);
		$b = $this->histogram([1 => 1]);

		HistogramMath::add($a, $b);

		$this->assertSame($this->histogram([1 => 1]), $a);
		$this->assertSame($this->histogram([1 => 1]), $b);
	}

	public function testAddingAnEmptyHistogramChangesNothing(): void
	{
		$a = $this->histogram([3 => 9]);

		$this->assertSame($a, HistogramMath::add($a, HistogramBins::empty()));
	}

	public function testSumFoldsEveryHistogramItIsGiven(): void
	{
		$sum = HistogramMath::sum([
			$this->histogram([0 => 1]),
			$this->histogram([0 => 2, 8 => 4]),
			$this->histogram([8 => 1]),
		]);

		$this->assertSame($this->histogram([0 => 3, 8 => 5]), $sum);
	}

	public function testSummingNothingIsAnEmptyHistogram(): void
	{
		$this->assertSame(HistogramBins::empty(), HistogramMath::sum([]));
	}

	public function testTotalIsTheNumberOfHitsRecorded(): void
	{
		$this->assertSame(0, HistogramMath::total(HistogramBins::empty()));
		$this->assertSame(12, HistogramMath::total($this->histogram([0 => 5, 9 => 7])));
	}

	/**
	 * A histogram comes off the wire or out of a JSON column, so it is the one place in the module
	 * where a malformed shape is a runtime possibility rather than a programming error.
	 *
	 * @dataProvider malformed
	 */
	public function testAMalformedHistogramIsRejected(array $histogram, string $expectedMessage): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessageMatches($expectedMessage);

		HistogramMath::add($histogram, HistogramBins::empty());
	}

	/** @return array<string, array{array, string}> */
	public static function malformed(): array
	{
		return [
			'too short'    => [array_fill(0, 15, 0), '/16 bins, got 15/'],
			'too long'     => [array_fill(0, 17, 0), '/16 bins, got 17/'],
			'string keys'  => [['a' => 0] + array_fill(1, 15, 0), '/bins 0 to 15/'],
			'sparse'       => [[0 => 1, 20 => 1] + array_fill(1, 14, 0), '/bins 0 to 15/'],
			'not a number' => [[0 => 'x'] + array_fill(1, 15, 0), '/integer counts/'],
			'float count'  => [[0 => 1.5] + array_fill(1, 15, 0), '/integer counts/'],
			'negative'     => [[0 => -1] + array_fill(1, 15, 0), '/negative/'],
		];
	}

	public function testNormaliseAcceptsWhatJsonDecodeReturns(): void
	{
		$fromJson = json_decode('[1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,2]', true);

		$this->assertSame($this->histogram([0 => 1, 15 => 2]), HistogramMath::normalise($fromJson));
	}

	public function testNormaliseRejectsNonsenseTheSameWayAddDoes(): void
	{
		$this->expectException(\InvalidArgumentException::class);

		HistogramMath::normalise(['nonsense']);
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
