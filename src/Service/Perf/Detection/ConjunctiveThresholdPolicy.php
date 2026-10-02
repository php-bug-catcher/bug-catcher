<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection;

use BugCatcher\Enum\PerfUnit;

/**
 * Every condition has to hold: enough traffic, a real rise, and a rise that is large both
 * relatively and absolutely.
 *
 * Conjunctive on purpose. A factor on its own alerts on 5 ms becoming 20 ms, which is four times
 * worse and nothing at all; an absolute floor on its own alerts on every route that was always
 * slow. Together they catch the thing worth a notification - something that was fine and is now
 * not - and stay quiet otherwise. A dashboard nobody trusts is worse than no dashboard.
 */
final readonly class ConjunctiveThresholdPolicy implements AnomalyPolicyInterface
{
	public function __construct(
		private float $factor,
		private int $minAbsoluteMs,
		private int $minHits,
	) {
	}

	public function isAnomalous(float $baseline, float $observed, int $hits, PerfUnit $unit): bool
	{
		if ($hits < $this->minHits || $observed <= $baseline) {
			return false;
		}

		// the floor is a duration, so it says nothing about a ratio or a byte count; for those the
		// factor and the hit count carry the decision on their own
		if ($unit === PerfUnit::Milliseconds && $observed < $this->minAbsoluteMs) {
			return false;
		}

		// a route that never errored and now does is a regression by any reading, and there is no
		// factor that multiplies zero into anything
		if ($baseline <= 0.0) {
			return true;
		}

		return $observed >= $baseline * $this->factor;
	}
}
