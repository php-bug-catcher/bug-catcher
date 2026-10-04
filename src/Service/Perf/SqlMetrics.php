<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf;

/**
 * The two extra metrics `php-bug-catcher/perf-collector-bundle` puts into every sample.
 *
 * Nothing about the ingest path knows these names - `perf_bucket_extra` takes whatever the
 * monitored application merged into `$GLOBALS['_bcperf_extra']`, and that is deliberate. They are
 * named here because one panel does have to know them: a chart cannot label an axis "sq" and a
 * reader cannot be told that 0.4 of something is seconds.
 *
 * **These are a wire format, like the histogram bin edges.** They are duplicated from
 * `BugCatcher\PerfCollectorBundle\EventListener\PublishSqlMetricsListener::QUERY_COUNT` and
 * `::QUERY_SECONDS`, which is not a dependency of this bundle - the server must read what an
 * installation already collected without the collector being installed beside it. Renaming one
 * here orphans every row written under the old name.
 */
final class SqlMetrics
{
	/** Queries the request ran, failed ones included. Summed over the bucket, so an integer count. */
	public const string QUERIES = 'sq';

	/** Seconds spent in the database, like every other duration on this wire. */
	public const string SECONDS = 'st';
}
