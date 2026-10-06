<?php

declare(strict_types=1);

namespace BugCatcher\Enum;

/**
 * What kind of work a project's performance row is describing.
 *
 * The two profiles are two different questions, and only one of them is about waiting:
 *
 * - {@see self::Web} — "are the people using this waiting". Apdex and p95 answer it, which is
 *   what the row has always shown.
 * - {@see self::Worker} — "is it still running, and is it slower than its own normal". A cron
 *   box has nobody waiting on it: messenger workers and import loops are *meant* to take
 *   minutes, so an absolute latency threshold reports a disaster every time they do their job.
 *
 * Apdex cannot be rescued for the second case by moving its threshold.
 * {@see \BugCatcher\Service\Perf\Report\Dto\PerfHealth::APDEX_THRESHOLD_MS} is pinned at 500 ms
 * because T and 4T both have to land on edges of
 * {@see \BugCatcher\Service\Perf\Histogram\HistogramBins::EDGES_MS} or the score starts
 * interpolating, and the only exact pairs on that scale are (25, 100), (250, 1000) and
 * (500, 2000). There is no cron-scale T. So a worker project gets a row made of the cells that
 * can answer it — a regression count against its own baseline, and a rate that falls when it
 * stops running — rather than an alarm that is always on.
 */
enum PerfProfile: string
{
	case Web    = 'web';
	case Worker = 'worker';
}
