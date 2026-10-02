<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Report\Dto;

use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Histogram\HistogramMath;

/**
 * How a bucket's requests were spread over five bands of "how long did you wait".
 *
 * This is what the histogram is for. A mean of 300 ms says nothing about whether every request
 * took 300 ms or nine took 50 and one took three seconds, and the second case is the one somebody
 * is complaining about. Stacked over time it shows the distribution shifting - the slow band
 * growing - which is the earliest visible sign of a regression.
 *
 * The five bands fall exactly on bin edges of {@see HistogramBins}, so nothing is interpolated
 * here: each band is a sum of whole bins.
 */
final readonly class LatencyBands
{
	/** Which bins each band is made of - the edges line up, so these are exact. */
	private const array BANDS = [
		'under100Ms' => [0, 1, 2, 3, 4, 5, 6],
		'to500Ms'    => [7, 8],
		'to2s'       => [9, 10],
		'to10s'      => [11, 12],
		'over10s'    => [13, 14, 15],
	];

	public function __construct(
		public int $under100Ms,
		public int $to500Ms,
		public int $to2s,
		public int $to10s,
		public int $over10s,
	) {
	}

	/** @param list<int> $histogram */
	public static function fromHistogram(array $histogram): self
	{
		$histogram = HistogramMath::normalise($histogram);
		$bands     = [];

		foreach (self::BANDS as $band => $bins) {
			$bands[$band] = 0;
			foreach ($bins as $bin) {
				$bands[$band] += $histogram[$bin];
			}
		}

		return new self(...$bands);
	}

	public static function empty(): self
	{
		return new self(0, 0, 0, 0, 0);
	}

	public function total(): int
	{
		return $this->under100Ms + $this->to500Ms + $this->to2s + $this->to10s + $this->over10s;
	}

	/**
	 * In the order they are stacked, fastest first, each with the label a legend prints.
	 *
	 * @return array<string, int>
	 */
	public function toArray(): array
	{
		return [
			'< 100 ms'    => $this->under100Ms,
			'100–500 ms'  => $this->to500Ms,
			'0.5–2 s'     => $this->to2s,
			'2–10 s'      => $this->to10s,
			'> 10 s'      => $this->over10s,
		];
	}
}
