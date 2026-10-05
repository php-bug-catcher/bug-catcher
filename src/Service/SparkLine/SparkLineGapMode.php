<?php

declare(strict_types=1);

namespace BugCatcher\Service\SparkLine;

/**
 * What a bucket nothing was reported for is worth.
 *
 * The answer depends on what the line measures, and getting it wrong is the difference between a
 * quiet night and an outage.
 */
enum SparkLineGapMode
{
	/**
	 * A counter: no row means zero. An interval nobody logged an error in had zero errors, which
	 * is a fact about the interval and belongs on the baseline.
	 */
	case Zero;

	/**
	 * A gauge: no row means nobody measured. Latency has no value for an hour with no traffic -
	 * a quiet night is not a fast night - so the last known value carries across and the line
	 * reads "unchanged" rather than "instant". Zero until the first real reading.
	 */
	case Hold;
}
