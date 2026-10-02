<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Detection;

use BugCatcher\Enum\PerfUnit;
use BugCatcher\Service\Perf\Detection\Extractor\AvgMetricExtractor;
use BugCatcher\Service\Perf\Detection\Extractor\ErrorRateMetricExtractor;
use BugCatcher\Service\Perf\Detection\Extractor\MemMetricExtractor;
use BugCatcher\Service\Perf\Detection\Extractor\P95MetricExtractor;
use BugCatcher\Service\Perf\Detection\MetricExtractorInterface;
use BugCatcher\Service\Perf\WindowAggregate;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Histogram\PercentileEstimator;
use PHPUnit\Framework\TestCase;

class MetricExtractorTest extends TestCase
{
	public function testEachMetricIsNamedTheWayTheConfigurationSpellsIt(): void
	{
		$this->assertSame('p95', $this->p95()->name());
		$this->assertSame('avg', (new AvgMetricExtractor())->name());
		$this->assertSame('error_rate', (new ErrorRateMetricExtractor())->name());
		$this->assertSame('mem', (new MemMetricExtractor())->name());
	}

	public function testEachMetricSaysWhatItsNumbersMean(): void
	{
		$this->assertSame(PerfUnit::Milliseconds, $this->p95()->unit());
		$this->assertSame(PerfUnit::Milliseconds, (new AvgMetricExtractor())->unit());
		$this->assertSame(PerfUnit::Ratio, (new ErrorRateMetricExtractor())->unit());
		$this->assertSame(PerfUnit::Bytes, (new MemMetricExtractor())->unit());
	}

	/** Bin 8 is `[250 ms, 500 ms)`, so a window that lived entirely in it has its p95 in there. */
	public function testTheP95ComesOutOfTheHistogram(): void
	{
		$p95 = $this->p95()->extract($this->stats(hits: 10, histogram: [8 => 10]));

		$this->assertNotNull($p95);
		$this->assertGreaterThanOrEqual(250.0, $p95);
		$this->assertLessThanOrEqual(500.0, $p95);
	}

	public function testTheAverageIsTheTotalTimePerHitInMilliseconds(): void
	{
		$this->assertSame(250.0, (new AvgMetricExtractor())->extract($this->stats(hits: 8, sumDuration: 2.0)));
	}

	public function testTheErrorRateCountsBothKindsOfError(): void
	{
		$this->assertSame(
			0.25,
			(new ErrorRateMetricExtractor())->extract($this->stats(hits: 8, clientErrors: 1, serverErrors: 1)),
		);
	}

	public function testMemoryIsThePeakOfTheAverageRequest(): void
	{
		$this->assertSame(
			1_048_576.0,
			(new MemMetricExtractor())->extract($this->stats(hits: 4, sumMem: 4_194_304)),
		);
	}

	/**
	 * A window nobody visited has no metric - not zero. Zero would read as "it got infinitely
	 * better" to a baseline and as a baseline of nothing to a threshold.
	 *
	 * @dataProvider everyExtractor
	 */
	public function testAWindowWithoutHitsHasNoMetricAtAll(MetricExtractorInterface $extractor): void
	{
		$this->assertNull($extractor->extract($this->stats(hits: 0)));
	}

	/** @return array<string, array{MetricExtractorInterface}> */
	public static function everyExtractor(): array
	{
		return [
			'p95'        => [new P95MetricExtractor(new PercentileEstimator())],
			'avg'        => [new AvgMetricExtractor()],
			'error_rate' => [new ErrorRateMetricExtractor()],
			'mem'        => [new MemMetricExtractor()],
		];
	}

	private function p95(): P95MetricExtractor
	{
		return new P95MetricExtractor(new PercentileEstimator());
	}

	private function stats(
		int $hits,
		float $sumDuration = 0.0,
		int $sumMem = 0,
		int $clientErrors = 0,
		int $serverErrors = 0,
		array $histogram = [],
	): WindowAggregate {
		$bins = HistogramBins::empty();
		foreach ($histogram as $bin => $count) {
			$bins[$bin] = $count;
		}

		return new WindowAggregate(
			'/checkout',
			md5('/checkout'),
			$hits,
			$sumDuration,
			0.0,
			0.0,
			0.0,
			$sumMem,
			0,
			$clientErrors,
			$serverErrors,
			$bins,
		);
	}
}
