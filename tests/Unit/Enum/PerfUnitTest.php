<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Enum;

use BugCatcher\Enum\PerfUnit;
use PHPUnit\Framework\TestCase;

class PerfUnitTest extends TestCase
{
	/**
	 * @dataProvider durations
	 */
	public function testMillisecondsReadAsAHumanWouldSayThem(float $value, string $expected): void
	{
		$this->assertSame($expected, PerfUnit::Milliseconds->format($value));
	}

	/** @return array<string, array{float, string}> */
	public static function durations(): array
	{
		return [
			'zero'                  => [0.0, '0 ms'],
			'sub millisecond'       => [0.42, '0.42 ms'],
			'single digit'          => [4.6, '4.6 ms'],
			'the design doc figure' => [210.0, '210 ms'],
			'just under a second'   => [999.4, '999 ms'],
			'the other figure'      => [3100.0, '3.1 s'],
			'a minute'              => [60000.0, '60 s'],
		];
	}

	/**
	 * @dataProvider ratios
	 */
	public function testRatiosReadAsPercentages(float $value, string $expected): void
	{
		$this->assertSame($expected, PerfUnit::Ratio->format($value));
	}

	/** @return array<string, array{float, string}> */
	public static function ratios(): array
	{
		return [
			'none'       => [0.0, '0%'],
			'a trickle'  => [0.0032, '0.32%'],
			'noticeable' => [0.125, '12.5%'],
			'everything' => [1.0, '100%'],
		];
	}

	/**
	 * @dataProvider byteSizes
	 */
	public function testBytesReadInBinaryUnits(float $value, string $expected): void
	{
		$this->assertSame($expected, PerfUnit::Bytes->format($value));
	}

	/** @return array<string, array{float, string}> */
	public static function byteSizes(): array
	{
		return [
			'nothing'  => [0.0, '0 B'],
			'bytes'    => [512.0, '512 B'],
			'kibibyte' => [2048.0, '2 KiB'],
			'mebibyte' => [31457280.0, '30 MiB'],
			'rounded'  => [1572864.0, '1.5 MiB'],
			'gibibyte' => [3221225472.0, '3 GiB'],
		];
	}

	public function testNegativeValuesKeepTheirSign(): void
	{
		$this->assertSame('-210 ms', PerfUnit::Milliseconds->format(-210.0));
		$this->assertSame('-2 KiB', PerfUnit::Bytes->format(-2048.0));
		$this->assertSame('-5%', PerfUnit::Ratio->format(-0.05));
	}

	public function testEveryUnitFormatsEverySaneValueWithoutBlowingUp(): void
	{
		foreach (PerfUnit::cases() as $unit) {
			foreach ([0.0, 1.0, 1234.5, 1.0e12] as $value) {
				$this->assertNotSame('', $unit->format($value));
			}
		}
	}
}
