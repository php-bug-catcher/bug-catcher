<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Report\Dto;

use BugCatcher\Service\Perf\PerfWindow;

/**
 * One project's window reduced to the handful of numbers a dashboard row has space for.
 *
 * Not {@see PerfTimeSeries}: a row is read from across an office and shows no shape, so it needs
 * the window as a whole rather than one point per bucket. The percentile is the reason this is a
 * read of its own and not a sum of the series - a percentile of percentiles is not a percentile,
 * so the bins are added first and the estimate taken once, off the total.
 *
 * Every number is nullable where "nothing arrived" is a different statement from zero. A quiet
 * night is not a fast night, and a row that printed `0 ms` for a project that shipped nothing
 * would be the most reassuring lie on the page.
 */
final readonly class PerfHealth
{
	/**
	 * The Apdex threshold, in milliseconds, and the only one this histogram can answer exactly.
	 *
	 * Apdex counts a request as satisfying up to T and tolerable up to 4T. 500 ms and 2 s are
	 * both bin edges of {@see \BugCatcher\Service\Perf\Histogram\HistogramBins}, so at T = 500 ms
	 * the score is a sum of whole bins with nothing interpolated. No other T has that property -
	 * T = 100 ms would want 400 ms, and the nearest edge is 500 - which is why this is a constant
	 * and not a setting: a configurable T would quietly start estimating.
	 */
	public const int APDEX_THRESHOLD_MS = 500;

	public function __construct(
		public PerfWindow $window,
		public int $hits,
		public ?float $p95Ms,
		public ?float $apdex,
		public ?float $errorRate,
	) {
	}

	public function isEmpty(): bool
	{
		return $this->hits === 0;
	}

	/**
	 * Requests per minute over the window, which is what makes two projects comparable - one
	 * watched over an hour and one over a day otherwise differ by a factor of 24.
	 */
	public function requestsPerMinute(): ?float
	{
		$minutes = ($this->window->to->getTimestamp() - $this->window->from->getTimestamp()) / 60;

		return $this->hits === 0 || $minutes <= 0 ? null : $this->hits / $minutes;
	}

	/**
	 * How the Apdex score reads, as the index's own four words.
	 *
	 * The bands are Apdex's, not ours: 0.94 excellent, 0.85 good, 0.70 fair, 0.50 poor. Worth
	 * keeping because they are what makes the number mean something to somebody who has not read
	 * this file - 0.91 is a good day, and no amount of colour explains that on its own.
	 */
	public function apdexRating(): ?string
	{
		return match (true) {
			$this->apdex === null  => null,
			$this->apdex >= 0.94   => 'excellent',
			$this->apdex >= 0.85   => 'good',
			$this->apdex >= 0.70   => 'fair',
			$this->apdex >= 0.50   => 'poor',
			default                => 'unacceptable',
		};
	}
}
