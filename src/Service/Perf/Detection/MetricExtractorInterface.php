<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection;

use BugCatcher\Enum\PerfUnit;
use BugCatcher\Service\Perf\WindowAggregate;

/**
 * One number worth watching, pulled out of what a route did over a window.
 *
 * The extension point behind `bug_catcher.perf.metrics`: implement this, register the service
 * under a name, and `anomaly.metric` can select it. Anything the monitored application ships in
 * `$GLOBALS['_bcperf_extra']` can be turned into a metric this way - see
 * docs/custom_perf_metric.md.
 */
interface MetricExtractorInterface
{
	/**
	 * The key this metric is configured and recorded under. It has to match the name it is
	 * registered with, because it is what ends up in `record_performance.metric`.
	 */
	public function name(): string;

	/** What the numbers mean - it decides how they are rendered and how a threshold reads them. */
	public function unit(): PerfUnit;

	/** @return float|null null when the window holds nothing this metric could be computed from */
	public function extract(WindowAggregate $window): ?float;
}
