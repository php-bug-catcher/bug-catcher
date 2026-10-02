<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf;

use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\PerfWindow;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PerfWindowTest extends TestCase
{
	public function testAWindowIsTwoInstantsAndTheWidthOfTheBucketsInIt(): void
	{
		$window = new PerfWindow(
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 15:00:00'),
			PerfGranularity::Hour,
		);

		$this->assertSame('2026-03-10 14:00:00', $window->from->format('Y-m-d H:i:s'));
		$this->assertSame('2026-03-10 15:00:00', $window->to->format('Y-m-d H:i:s'));
		$this->assertSame(PerfGranularity::Hour, $window->granularity);
	}

	/**
	 * The end is exclusive, which is what makes consecutive windows tile instead of overlap: the
	 * hour 14:00 is `[14:00, 15:00)` and the row at 15:00 belongs to the next one.
	 */
	public function testTheEndIsExclusive(): void
	{
		$window = $this->window('2026-03-10 14:00:00', '2026-03-10 15:00:00');

		$this->assertTrue($window->contains(new DateTimeImmutable('2026-03-10 14:00:00')));
		$this->assertTrue($window->contains(new DateTimeImmutable('2026-03-10 14:59:59')));
		$this->assertFalse($window->contains(new DateTimeImmutable('2026-03-10 15:00:00')));
		$this->assertFalse($window->contains(new DateTimeImmutable('2026-03-10 13:59:59')));
	}

	public function testAWindowThatEndsBeforeItStartsIsNotAWindow(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->window('2026-03-10 15:00:00', '2026-03-10 14:00:00');
	}

	/**
	 * An empty window would read as "roll up nothing" and quietly do nothing at all, which is the
	 * worst answer a cron job can give.
	 */
	public function testAWindowOfNoWidthIsNotAWindow(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->window('2026-03-10 14:00:00', '2026-03-10 14:00:00');
	}

	private function window(string $from, string $to): PerfWindow
	{
		return new PerfWindow(new DateTimeImmutable($from), new DateTimeImmutable($to), PerfGranularity::Hour);
	}
}
