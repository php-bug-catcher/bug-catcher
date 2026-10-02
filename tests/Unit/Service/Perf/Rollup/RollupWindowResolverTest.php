<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Rollup;

use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\PerfWindow;
use BugCatcher\Service\Perf\Rollup\RollupWindowResolver;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class RollupWindowResolverTest extends TestCase
{
	/**
	 * The default is what cron runs at `:17` - the hour that has just finished, never the one
	 * still filling up. A partial hour would be stored and then silently replaced by the next run;
	 * leaving it alone means the stored hour is always the whole hour.
	 */
	public function testWithoutBoundsItIsTheLastCompletedBucket(): void
	{
		$window = $this->resolver('2026-03-10 15:17:03')->resolve(PerfGranularity::Hour);

		$this->assertWindow('2026-03-10 14:00:00', '2026-03-10 15:00:00', $window);
		$this->assertSame(PerfGranularity::Hour, $window->granularity);
	}

	public function testTheLastCompletedDayIsYesterday(): void
	{
		$window = $this->resolver('2026-03-10 04:23:00')->resolve(PerfGranularity::Day);

		$this->assertWindow('2026-03-09 00:00:00', '2026-03-10 00:00:00', $window);
	}

	/**
	 * `--to` names a bucket, not a boundary: `--granularity=day --from=2026-03-01 --to=2026-03-05`
	 * is five days, because that is what an operator typing it means.
	 */
	public function testBothBoundsCoverTheBucketTheyName(): void
	{
		$window = $this->resolver('2026-03-10 15:17:03')
			->resolve(PerfGranularity::Day, '2026-03-01', '2026-03-05');

		$this->assertWindow('2026-03-01 00:00:00', '2026-03-06 00:00:00', $window);
	}

	public function testABoundInThemiddleOfABucketCoversThatWholeBucket(): void
	{
		$window = $this->resolver('2026-03-10 15:17:03')
			->resolve(PerfGranularity::Hour, '2026-03-10 09:41:00', '2026-03-10 11:02:00');

		$this->assertWindow('2026-03-10 09:00:00', '2026-03-10 12:00:00', $window);
	}

	public function testOnlyAStartMeansEverythingUpToTheLastCompletedBucket(): void
	{
		$window = $this->resolver('2026-03-10 15:17:03')->resolve(PerfGranularity::Hour, '2026-03-10 12:00:00');

		$this->assertWindow('2026-03-10 12:00:00', '2026-03-10 15:00:00', $window);
	}

	public function testOnlyAnEndMeansThatOneBucket(): void
	{
		$window = $this->resolver('2026-03-10 15:17:03')->resolve(PerfGranularity::Hour, null, '2026-03-08 07:30:00');

		$this->assertWindow('2026-03-08 07:00:00', '2026-03-08 08:00:00', $window);
	}

	public function testAnEndBeforeTheStartIsRefused(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->resolver('2026-03-10 15:17:03')
			->resolve(PerfGranularity::Hour, '2026-03-10 12:00:00', '2026-03-10 09:00:00');
	}

	/**
	 * A date the operator mistyped has to say so. Left to `DateTimeImmutable` it would either throw
	 * something unreadable or, worse, parse into a date nobody meant.
	 */
	public function testSomethingThatIsNotADateIsRefusedByName(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/--from/');

		$this->resolver('2026-03-10 15:17:03')->resolve(PerfGranularity::Hour, 'last tuesdya');
	}

	/** Minutes are what the collector ships; nothing rolls up into them. */
	public function testThereIsNoWindowThatRollsUpIntoMinutes(): void
	{
		$this->expectException(InvalidArgumentException::class);

		$this->resolver('2026-03-10 15:17:03')->resolve(PerfGranularity::Minute);
	}

	private function resolver(string $now): RollupWindowResolver
	{
		return new RollupWindowResolver(new MockClock($now));
	}

	private function assertWindow(string $from, string $to, PerfWindow $window): void
	{
		$this->assertSame($from, $window->from->format('Y-m-d H:i:s'), 'window start');
		$this->assertSame($to, $window->to->format('Y-m-d H:i:s'), 'window end');
	}
}
