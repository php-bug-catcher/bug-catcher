<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Report\Dto;

use BugCatcher\Service\Perf\SqlMetrics;

/**
 * The database half of a window: what the collector bundle counted under {@see SqlMetrics}.
 *
 * Built once by {@see \BugCatcher\Service\Perf\Report\PerfReportBuilder::database()} for the panel
 * and the MCP tools alike, so the two cannot come to disagree about what a share of the response
 * is. It carries its series because the panel draws it and reading the window twice would be a
 * second query for the same rows.
 */
final readonly class DatabaseReport
{
	/**
	 * @param list<TopPathRow> $routes the heaviest of the busiest routes, by time in the database
	 */
	public function __construct(
		public PerfTimeSeries $series,
		public array $routes,
	) {
	}

	public function isEmpty(): bool
	{
		return $this->series->isEmpty();
	}

	/**
	 * Whether anything in the window counted queries at all. Traffic without it is the collector
	 * bundle missing from the monitored application - a different problem from a quiet window,
	 * and one that reads identically unless somebody says so.
	 */
	public function isCollected(): bool
	{
		foreach ($this->series->points as $point) {
			if (isset($point->extra[SqlMetrics::QUERIES]) || isset($point->extra[SqlMetrics::SECONDS])) {
				return true;
			}
		}

		return false;
	}

	public function queries(): float
	{
		return $this->total(SqlMetrics::QUERIES);
	}

	public function queriesPerHit(): float
	{
		return $this->perHit($this->total(SqlMetrics::QUERIES));
	}

	public function dbMsPerHit(): float
	{
		return $this->perHit($this->total(SqlMetrics::SECONDS)) * 1000;
	}

	/**
	 * Capped at the whole: wallclock and query time are measured by different clocks, so the
	 * database can come out a hair longer than the response it was part of.
	 */
	public function shareOfWallclock(): float
	{
		$wallMs = array_sum(array_map(
			static fn(PerfTimePoint $point): float => $point->avgMs * $point->hits,
			$this->series->points,
		));

		return $wallMs <= 0.0 ? 0.0 : min(1.0, $this->total(SqlMetrics::SECONDS) * 1000 / $wallMs);
	}

	public static function shareOf(TopPathRow $row): float
	{
		$dbMs = ($row->extraTotal(SqlMetrics::SECONDS) ?? 0.0) * 1000;

		return $row->totalMs <= 0.0 ? 0.0 : min(1.0, $dbMs / $row->totalMs);
	}

	public static function queriesPerHitOf(TopPathRow $row): ?float
	{
		return $row->extraPerHit(SqlMetrics::QUERIES);
	}

	public static function dbMsPerHitOf(TopPathRow $row): ?float
	{
		$perHit = $row->extraPerHit(SqlMetrics::SECONDS);

		return $perHit === null ? null : $perHit * 1000;
	}

	private function total(string $metric): float
	{
		return array_sum(array_map(
			static fn(PerfTimePoint $point): float => $point->extra[$metric] ?? 0.0,
			$this->series->points,
		));
	}

	private function perHit(float $total): float
	{
		$hits = $this->series->hits();

		return $hits === 0 ? 0.0 : $total / $hits;
	}
}
