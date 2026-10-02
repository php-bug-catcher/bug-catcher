<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Report;

use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Report\GranularityResolver;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GranularityResolverTest extends TestCase
{
	/**
	 * The point is a readable chart rather than all the detail there is: two hours of minutes is
	 * 120 points, a week of hours is 168, and a year of days is 365. Any other pairing is either
	 * a flat line or a smear.
	 *
	 * @dataProvider windows
	 */
	public function testTheWidthOfTheWindowDecidesHowFineItIsRead(string $to, PerfGranularity $expected): void
	{
		$this->assertSame(
			$expected,
			(new GranularityResolver())->forWindow(
				new DateTimeImmutable('2026-03-10 00:00:00'),
				new DateTimeImmutable($to),
			),
		);
	}

	/** @return array<string, array{string, PerfGranularity}> */
	public static function windows(): array
	{
		return [
			'a minute'            => ['2026-03-10 00:01:00', PerfGranularity::Minute],
			'two hours exactly'   => ['2026-03-10 02:00:00', PerfGranularity::Minute],
			'two hours and a bit' => ['2026-03-10 02:00:01', PerfGranularity::Hour],
			'a day'               => ['2026-03-11 00:00:00', PerfGranularity::Hour],
			'seven days exactly'  => ['2026-03-17 00:00:00', PerfGranularity::Hour],
			'eight days'          => ['2026-03-18 00:00:00', PerfGranularity::Day],
			'a year'              => ['2027-03-10 00:00:00', PerfGranularity::Day],
		];
	}

	public function testAWindowThatEndsBeforeItStartsIsNotAWindow(): void
	{
		$this->expectException(InvalidArgumentException::class);

		(new GranularityResolver())->forWindow(
			new DateTimeImmutable('2026-03-10 02:00:00'),
			new DateTimeImmutable('2026-03-10 00:00:00'),
		);
	}

	public function testItHandsBackAWindowReadyToRead(): void
	{
		$window = (new GranularityResolver())->window(
			new DateTimeImmutable('2026-03-10 00:00:00'),
			new DateTimeImmutable('2026-03-11 00:00:00'),
		);

		$this->assertSame(PerfGranularity::Hour, $window->granularity);
		$this->assertSame('2026-03-10 00:00:00', $window->from->format('Y-m-d H:i:s'));
		$this->assertSame('2026-03-11 00:00:00', $window->to->format('Y-m-d H:i:s'));
	}

	/**
	 * A window read as hours has to start on an hour, or the first bucket holds part of a bucket
	 * and the chart opens with a dip that is not there.
	 */
	public function testTheEdgesAreMovedOntoBucketBoundaries(): void
	{
		$window = (new GranularityResolver())->window(
			new DateTimeImmutable('2026-03-10 09:41:00'),
			new DateTimeImmutable('2026-03-11 11:02:00'),
		);

		$this->assertSame(PerfGranularity::Hour, $window->granularity);
		$this->assertSame('2026-03-10 09:00:00', $window->from->format('Y-m-d H:i:s'));
		$this->assertSame('2026-03-11 12:00:00', $window->to->format('Y-m-d H:i:s'));
	}
}
