<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Report\Dto;

use BugCatcher\Service\Perf\PerfWindow;

/**
 * The window and one point per bucket in it, which is what all four charts of the overview are
 * drawn from.
 *
 * Every bucket in the window is present, including the ones nothing happened in: a chart that
 * simply omits them draws a line straight across an outage, which is the opposite of what the
 * page is for.
 */
final readonly class PerfTimeSeries
{
	/** @param list<PerfTimePoint> $points one per bucket, oldest first */
	public function __construct(
		public PerfWindow $window,
		public array $points,
	) {
	}

	public function isEmpty(): bool
	{
		return $this->hits() === 0;
	}

	public function hits(): int
	{
		return array_sum(array_map(static fn(PerfTimePoint $point): int => $point->hits, $this->points));
	}

	/** @return list<string> the x axis, formatted for the width of the bucket */
	public function labels(): array
	{
		$format = match ($this->window->granularity) {
			\BugCatcher\Enum\PerfGranularity::Minute => 'H:i',
			\BugCatcher\Enum\PerfGranularity::Hour   => 'd.m. H:i',
			\BugCatcher\Enum\PerfGranularity::Day    => 'd.m.Y',
		};

		return array_map(
			static fn(PerfTimePoint $point): string => $point->bucketAt->format($format),
			$this->points,
		);
	}

	/**
	 * One named series across the points, ready for a chart.
	 *
	 * @param callable(PerfTimePoint): (int|float|null) $value
	 * @return list<int|float|null>
	 */
	public function series(callable $value): array
	{
		return array_map($value, $this->points);
	}
}
