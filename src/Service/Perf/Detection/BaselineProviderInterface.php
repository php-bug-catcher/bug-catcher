<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection;

use BugCatcher\Entity\Project;
use BugCatcher\Service\Perf\PerfWindow;

/**
 * What a metric used to be, for a route that is being looked at now.
 *
 * Separate from the detector because "normal" is an installation's own idea. Alias this interface
 * to compute it differently - a rolling mean, a published SLO, a number out of a config file.
 */
interface BaselineProviderInterface
{
	/**
	 * @param string $pathHash {@see \BugCatcher\Entity\PerfBucket::hashPath()} of the route
	 * @param PerfWindow $window the window being judged, not the window the baseline is read from
	 * @return float|null null when there is no history to compare against
	 */
	public function baselineFor(
		Project $project,
		string $pathHash,
		PerfWindow $window,
		MetricExtractorInterface $metric,
	): ?float;
}
