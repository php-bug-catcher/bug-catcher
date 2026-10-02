<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Detection;

use BugCatcher\Service\Perf\Detection\Extractor\AvgMetricExtractor;
use BugCatcher\Service\Perf\Detection\Extractor\ErrorRateMetricExtractor;
use BugCatcher\Service\Perf\Detection\MetricExtractorRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MetricExtractorRegistryTest extends TestCase
{
	public function testAMetricIsFoundByTheNameTheConfigurationUses(): void
	{
		$avg = new AvgMetricExtractor();

		$this->assertSame($avg, $this->registry(['avg' => $avg])->get('avg'));
	}

	/**
	 * `anomaly.metric` is a string an operator typed. Answering with the list of what exists is
	 * the difference between a one-line fix and reading the source.
	 */
	public function testAMetricNobodyRegisteredSaysWhatThereIs(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/avg, error_rate/');

		$this->registry(['avg' => new AvgMetricExtractor(), 'error_rate' => new ErrorRateMetricExtractor()])
			->get('p96');
	}

	public function testTheRegistryKnowsWhatItHolds(): void
	{
		$registry = $this->registry(['avg' => new AvgMetricExtractor()]);

		$this->assertTrue($registry->has('avg'));
		$this->assertFalse($registry->has('p95'));
		$this->assertSame(['avg'], $registry->names());
	}

	/**
	 * The map is keyed by configuration name, but an extractor also carries one, and two different
	 * answers to "what is this metric called" end up in the `metric` column of a record.
	 */
	public function testAnExtractorFiledUnderANameThatIsNotItsOwnIsRefused(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->registry(['average' => new AvgMetricExtractor()]);
	}

	/** @param array<string, \BugCatcher\Service\Perf\Detection\MetricExtractorInterface> $extractors */
	private function registry(array $extractors): MetricExtractorRegistry
	{
		return new MetricExtractorRegistry($extractors);
	}
}
