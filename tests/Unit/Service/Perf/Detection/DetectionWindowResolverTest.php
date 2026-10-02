<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Detection;

use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Detection\DetectionWindowResolver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class DetectionWindowResolverTest extends TestCase
{
	/**
	 * The minute that is still filling up is left out: it holds a fraction of its traffic, which
	 * is the shortest road to an alert on a route that is perfectly fine.
	 */
	public function testTheWindowEndsAtTheLastCompletedMinute(): void
	{
		$window = $this->resolver('2026-03-10 14:40:37')->lastCompleted(5);

		$this->assertSame('2026-03-10 14:35:00', $window->from->format('Y-m-d H:i:s'));
		$this->assertSame('2026-03-10 14:40:00', $window->to->format('Y-m-d H:i:s'));
		$this->assertSame(PerfGranularity::Minute, $window->granularity);
	}

	/** Whatever the cron interval is, the window has to match it or the gaps go unexamined. */
	public function testTheWidthIsWhateverItWasAskedFor(): void
	{
		$window = $this->resolver('2026-03-10 14:40:37')->lastCompleted(15);

		$this->assertSame('2026-03-10 14:25:00', $window->from->format('Y-m-d H:i:s'));
		$this->assertSame('2026-03-10 14:40:00', $window->to->format('Y-m-d H:i:s'));
	}

	public function testAWindowOfNoMinutesIsNotAWindow(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->resolver('2026-03-10 14:40:37')->lastCompleted(0);
	}

	private function resolver(string $now): DetectionWindowResolver
	{
		return new DetectionWindowResolver(new MockClock($now));
	}
}
