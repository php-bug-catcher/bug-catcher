<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Enum;

use BugCatcher\Enum\PerfMetric;
use BugCatcher\Enum\PerfUnit;
use PHPUnit\Framework\TestCase;

class PerfMetricTest extends TestCase
{
	public function testTheMetricNamesAreTheOnesConfigurationAndTheSchemaUse(): void
	{
		$this->assertSame(
			['p95', 'avg', 'error_rate', 'mem'],
			array_map(fn(PerfMetric $m) => $m->value, PerfMetric::cases()),
		);
	}

	/**
	 * @dataProvider units
	 */
	public function testUnitDrivesThresholdSemantics(PerfMetric $metric, PerfUnit $expected): void
	{
		$this->assertSame($expected, $metric->unit());
	}

	/** @return array<string, array{PerfMetric, PerfUnit}> */
	public static function units(): array
	{
		return [
			'p95'        => [PerfMetric::P95, PerfUnit::Milliseconds],
			'avg'        => [PerfMetric::Avg, PerfUnit::Milliseconds],
			'error rate' => [PerfMetric::ErrorRate, PerfUnit::Ratio],
			'memory'     => [PerfMetric::Mem, PerfUnit::Bytes],
		];
	}

	public function testEveryMetricHasALabelOfItsOwn(): void
	{
		$labels = array_map(fn(PerfMetric $m) => $m->label(), PerfMetric::cases());

		$this->assertCount(count(PerfMetric::cases()), array_unique($labels));
		$this->assertNotContains('', $labels);
	}

	public function testFormatDelegatesToTheUnit(): void
	{
		$this->assertSame('210 ms', PerfMetric::P95->format(210.0));
		$this->assertSame('12.5%', PerfMetric::ErrorRate->format(0.125));
		$this->assertSame('30 MiB', PerfMetric::Mem->format(31457280.0));
	}
}
