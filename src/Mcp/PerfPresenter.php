<?php

namespace BugCatcher\Mcp;

use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\PerfWindow;
use BugCatcher\Service\Perf\Report\Dto\DatabaseReport;
use BugCatcher\Service\Perf\Report\Dto\LatencyBands;
use BugCatcher\Service\Perf\Report\Dto\PerfHealth;
use BugCatcher\Service\Perf\Report\Dto\PerfSummary;
use BugCatcher\Service\Perf\Report\Dto\PerfTimePoint;
use BugCatcher\Service\Perf\Report\Dto\TopPathRow;
use BugCatcher\Service\Perf\SqlMetrics;

/**
 * The performance reports as the flat arrays an MCP client reads.
 *
 * Units are in the key, because a client reading `p95` has no axis label to look at: `Ms` is
 * milliseconds, `Bytes` bytes, and a `Rate`, `share` or `apdex` is a ratio from 0 to 1. Rounded,
 * because sixteen digits of a mean are noise an assistant pays for by the token.
 *
 * Nothing here computes. Every number is the report builder's, so what the tools say is what the
 * performance page says.
 */
final class PerfPresenter
{
	/** The same wall clock the record tools print - the stored columns carry no timezone. */
	public const DATE_FORMAT = 'Y-m-d H:i:s';

	/**
	 * @return array{from: string, to: string, granularity: string}
	 */
	public function window(PerfWindow $window): array {
		return [
			'from'        => $window->from->format(self::DATE_FORMAT),
			'to'          => $window->to->format(self::DATE_FORMAT),
			'granularity' => $window->granularity->value,
		];
	}

	/**
	 * Why an empty answer may not mean a quiet window, or null when it does.
	 *
	 * Anything wider than two hours is read from rolled-up rows, and an installation whose cron does
	 * not run `app:perf:rollup` has none - while the minutes sit in the table. Said here because the
	 * reader cannot see the table.
	 */
	public function emptyNote(PerfWindow $window): ?string {
		if ($window->granularity === PerfGranularity::Minute) {
			return null;
		}

		return sprintf(
			'Nothing at %s granularity. Windows over two hours are read from rolled-up buckets: if this '
			. 'should have traffic, app:perf:rollup may not be running. A window of two hours or less '
			. 'reads the raw minutes.',
			$window->granularity->value,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function health(PerfHealth $health): array {
		return [
			'hits'              => $health->hits,
			'requestsPerMinute' => $this->round($health->requestsPerMinute()),
			'p95Ms'             => $this->round($health->p95Ms),
			'apdex'             => $this->ratio($health->apdex),
			'apdexRating'       => $health->apdexRating(),
			'errorRate'         => $this->ratio($health->errorRate),
		];
	}

	/**
	 * One bucket, or a whole window read as one.
	 *
	 * @return array<string, mixed>
	 */
	public function point(PerfTimePoint $point): array {
		return [
			'bucketAt'       => $point->bucketAt->format(self::DATE_FORMAT),
			'hits'           => $point->hits,
			'avgMs'          => $this->round($point->avgMs),
			'p95Ms'          => $this->round($point->p95Ms),
			'userCpuMs'      => $this->round($point->userMs),
			'sysCpuMs'       => $this->round($point->sysMs),
			// null is "the machine reports no CPU" (Windows), not "no waiting"
			'waitMs'         => $this->round($point->waitMs),
			'memPerHitBytes' => (int)round($point->memPerHit),
			'maxMemBytes'    => $point->maxMem,
			'status'         => [
				'ok'           => $point->ok,
				'clientErrors' => $point->clientErrors,
				'serverErrors' => $point->serverErrors,
			],
			'errorRate'      => $this->ratio($point->errorRate()),
			'latencyBands'   => $this->bands($point->bands),
			'database'       => $this->database(
				$point->extraPerHit(SqlMetrics::QUERIES),
				$point->extraPerHit(SqlMetrics::SECONDS),
				$point->avgMs,
			),
			'extra'          => $this->extra($point->extra),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function summary(PerfSummary $summary): array {
		return $this->point($summary->total) + [
				'maxMs' => $this->round($summary->maxMs),
				'apdex' => $this->ratio($summary->apdex),
			];
	}

	/**
	 * A line of the top paths table, per request and in total both - the page's "per hit" toggle
	 * is presentation, and a client can use either.
	 *
	 * @return array<string, mixed>
	 */
	public function row(TopPathRow $row): array {
		return [
			'label'          => $row->label,
			'hits'           => $row->hits,
			'totalMs'        => $this->round($row->totalMs),
			'avgMs'          => $this->round($row->msPerHit()),
			'p95Ms'          => $this->round($row->p95Ms),
			'maxMs'          => $this->round($row->maxMs),
			'userCpuMs'      => $this->round($row->userMsPerHit()),
			'sysCpuMs'       => $this->round($row->sysMsPerHit()),
			'waitMs'         => $this->round($row->waitMsPerHit()),
			'memPerHitBytes' => (int)round($row->memPerHit()),
			'maxMemBytes'    => $row->maxMem,
			'errorRate'      => $this->ratio($row->errorRate),
			'database'       => $this->database(
				DatabaseReport::queriesPerHitOf($row),
				$row->extraPerHit(SqlMetrics::SECONDS),
				$row->msPerHit(),
			),
			'extra'          => $this->extra($row->extra),
		];
	}

	/**
	 * Per request, or null where nothing counted queries - which is the collector bundle missing,
	 * not a route that runs none.
	 *
	 * @return array{queriesPerHit: float|null, msPerHit: float|null, shareOfResponse: float|null}|null
	 */
	private function database(?float $queriesPerHit, ?float $secondsPerHit, float $avgMs): ?array {
		if ($queriesPerHit === null && $secondsPerHit === null) {
			return null;
		}

		$dbMs = $secondsPerHit === null ? null : $secondsPerHit * 1000;

		return [
			'queriesPerHit'   => $this->round($queriesPerHit),
			'msPerHit'        => $this->round($dbMs),
			// capped, the way the panel caps it: two clocks, and the database can come out a hair
			// longer than the response it was part of
			'shareOfResponse' => $dbMs === null || $avgMs <= 0.0 ? null : $this->ratio(min(1.0, $dbMs / $avgMs)),
		];
	}

	/** @return array<string, int> */
	private function bands(LatencyBands $bands): array {
		return [
			'under100Ms' => $bands->under100Ms,
			'to500Ms'    => $bands->to500Ms,
			'to2s'       => $bands->to2s,
			'to10s'      => $bands->to10s,
			'over10s'    => $bands->over10s,
		];
	}

	/**
	 * Totals over the slice, in whatever unit the application counted them - the bundle does not
	 * know, so it does not divide them.
	 *
	 * @param array<string, float> $extra
	 * @return array<string, float>
	 */
	private function extra(array $extra): array {
		return array_map(fn(float $value): float => (float)$this->round($value), $extra);
	}

	public function round(?float $value): ?float {
		return $value === null ? null : round($value, 2);
	}

	public function ratio(?float $value): ?float {
		return $value === null ? null : round($value, 4);
	}
}
