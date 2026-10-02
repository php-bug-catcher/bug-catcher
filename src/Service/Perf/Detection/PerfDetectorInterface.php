<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection;

use BugCatcher\Entity\Project;
use BugCatcher\Service\Perf\PerfWindow;

/**
 * Something that looks at a window of measurements and says what is wrong with it.
 *
 * The pluggable unit of detection: implement this, register the service under a name in
 * `bug_catcher.perf.detector_services` and switch it on in `bug_catcher.perf.detectors`. An SLO
 * burn rate, a traffic drop, memory creeping up release after release - anything that can read
 * buckets and name a route fits here, and what it yields flows into records, notifiers and MCP
 * with no further code.
 */
interface PerfDetectorInterface
{
	/** The key `bug_catcher.perf.detectors` switches it on by. */
	public function name(): string;

	/** @return iterable<AnomalyFinding> */
	public function detect(Project $project, PerfWindow $window): iterable;
}
