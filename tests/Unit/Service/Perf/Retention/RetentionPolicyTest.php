<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Retention;

use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Retention\RetentionPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class RetentionPolicyTest extends TestCase
{
	public function testEachGranularityIsKeptForAsLongAsItWasConfigured(): void
	{
		$policy = $this->policy(['minute' => '7 days', 'hour' => '90 days', 'day' => '2 years']);

		$this->assertCutoff('2026-03-03 15:17:03', $policy->cutoff(PerfGranularity::Minute));
		$this->assertCutoff('2025-12-10 15:17:03', $policy->cutoff(PerfGranularity::Hour));
		$this->assertCutoff('2024-03-10 15:17:03', $policy->cutoff(PerfGranularity::Day));
	}

	public function testTheCutoffMovesWithTheClock(): void
	{
		$clock  = new MockClock('2026-03-10 15:17:03');
		$policy = new RetentionPolicy(['minute' => '1 day', 'hour' => '1 day', 'day' => '1 day'], $clock);

		$this->assertCutoff('2026-03-09 15:17:03', $policy->cutoff(PerfGranularity::Minute));

		$clock->sleep(3600);

		$this->assertCutoff('2026-03-09 16:17:03', $policy->cutoff(PerfGranularity::Minute));
	}

	/**
	 * A typo in the configuration must stop the command rather than be read as "keep nothing":
	 * what is on the other side of this value is a DELETE.
	 *
	 * @dataProvider unusableDurations
	 */
	public function testADurationThatIsNotADurationIsRefused(string $duration): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/minute/');

		$this->policy(['minute' => $duration, 'hour' => '90 days', 'day' => '2 years']);
	}

	/** @return array<string, array{string}> */
	public static function unusableDurations(): array
	{
		return [
			'nonsense'    => ['last tuesdya'],
			'empty'       => [''],
			// would delete everything that is not in the future
			'no duration' => ['0 days'],
			// would delete rows that have not been written yet
			'backwards'   => ['-7 days'],
		];
	}

	public function testAGranularityWithoutARetentionIsRefused(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('/day/');

		$this->policy(['minute' => '7 days', 'hour' => '90 days']);
	}

	/** @param array<string, string> $retention */
	private function policy(array $retention): RetentionPolicy
	{
		return new RetentionPolicy($retention, new MockClock('2026-03-10 15:17:03'));
	}

	private function assertCutoff(string $expected, \DateTimeImmutable $cutoff): void
	{
		$this->assertSame($expected, $cutoff->format('Y-m-d H:i:s'));
	}
}
