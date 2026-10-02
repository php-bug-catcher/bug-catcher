<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Enum;

use BugCatcher\Enum\PerfGranularity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class PerfGranularityTest extends TestCase
{
	public function testTheThreeGranularitiesAreTheOnesTheSchemaStores(): void
	{
		$this->assertSame(
			['minute', 'hour', 'day'],
			array_map(fn(PerfGranularity $g) => $g->value, PerfGranularity::cases()),
		);
	}

	/**
	 * @dataProvider intervals
	 */
	public function testIntervalIsTheWidthOfOneBucket(PerfGranularity $granularity, string $expected): void
	{
		$from = new DateTimeImmutable('2026-03-10 14:00:00');

		$this->assertSame($expected, $from->add($granularity->interval())->format('Y-m-d H:i:s'));
	}

	/** @return array<string, array{PerfGranularity, string}> */
	public static function intervals(): array
	{
		return [
			'minute' => [PerfGranularity::Minute, '2026-03-10 14:01:00'],
			'hour'   => [PerfGranularity::Hour, '2026-03-10 15:00:00'],
			'day'    => [PerfGranularity::Day, '2026-03-11 14:00:00'],
		];
	}

	public function testIntervalIsAFreshObjectEveryCall(): void
	{
		$granularity = PerfGranularity::Minute;

		$this->assertNotSame($granularity->interval(), $granularity->interval());
	}

	public function testCoarserIsTheRollupTargetAndDayIsTheTop(): void
	{
		$this->assertSame(PerfGranularity::Hour, PerfGranularity::Minute->coarser());
		$this->assertSame(PerfGranularity::Day, PerfGranularity::Hour->coarser());
		$this->assertNull(PerfGranularity::Day->coarser());
	}

	public function testFinerIsTheRollupSourceAndMinuteIsTheBottom(): void
	{
		$this->assertSame(PerfGranularity::Hour, PerfGranularity::Day->finer());
		$this->assertSame(PerfGranularity::Minute, PerfGranularity::Hour->finer());
		$this->assertNull(PerfGranularity::Minute->finer());
	}

	/**
	 * @dataProvider floors
	 */
	public function testFloorPutsATimestampOnTheBucketBoundary(
		PerfGranularity $granularity,
		string $expected,
	): void {
		$at = new DateTimeImmutable('2026-03-10 14:37:41.123456');

		$this->assertSame($expected, $granularity->floor($at)->format('Y-m-d H:i:s.u'));
	}

	/** @return array<string, array{PerfGranularity, string}> */
	public static function floors(): array
	{
		return [
			'minute' => [PerfGranularity::Minute, '2026-03-10 14:37:00.000000'],
			'hour'   => [PerfGranularity::Hour, '2026-03-10 14:00:00.000000'],
			'day'    => [PerfGranularity::Day, '2026-03-10 00:00:00.000000'],
		];
	}

	public function testFloorIsIdempotent(): void
	{
		$at = new DateTimeImmutable('2026-03-10 14:37:41.123456');

		foreach (PerfGranularity::cases() as $granularity) {
			$once = $granularity->floor($at);

			$this->assertEquals($once, $granularity->floor($once));
		}
	}

	public function testFloorKeepsTheTimezoneItWasGiven(): void
	{
		$at = new DateTimeImmutable('2026-03-10 14:37:41', new \DateTimeZone('Europe/Bratislava'));

		$floored = PerfGranularity::Hour->floor($at);

		$this->assertSame('Europe/Bratislava', $floored->getTimezone()->getName());
		$this->assertSame('2026-03-10 14:00:00', $floored->format('Y-m-d H:i:s'));
	}
}
