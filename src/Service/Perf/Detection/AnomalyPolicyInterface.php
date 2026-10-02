<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection;

use BugCatcher\Enum\PerfUnit;

/**
 * When a number that got worse is worth telling somebody about.
 *
 * Separate from the detector because "what changed" and "does anybody care" are different
 * questions with different answers per installation. Alias this interface to swap the whole
 * judgement, the way `BatchRecordDeleteInterface` is swapped today.
 */
interface AnomalyPolicyInterface
{
	/**
	 * @param float $baseline what the metric used to be
	 * @param float $observed what it is now
	 * @param int $hits how many requests the observation rests on
	 * @param PerfUnit $unit what the two numbers mean
	 */
	public function isAnomalous(float $baseline, float $observed, int $hits, PerfUnit $unit): bool;
}
