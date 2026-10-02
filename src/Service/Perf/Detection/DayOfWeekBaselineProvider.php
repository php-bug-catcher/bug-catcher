<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection;

use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\PerfWindow;
use DateInterval;

/**
 * Normal is what this route did at this time of day on the last few same weekdays.
 *
 * Matched on day of week because traffic is: Monday morning is not Sunday night, and a baseline
 * that ignores the weekly shape alerts every Monday. Taken as the **median** of the weeks rather
 * than the mean, so a single bad week - a deployment, an import, an outage - cannot raise the bar
 * for a month.
 *
 * It reads **hour** buckets, not the minutes the observed window is made of. Minutes are kept for
 * a week by default and hours for ninety days, so a baseline built from minutes would quietly
 * stop existing exactly when the retention caught up with it. The hour containing the same clock
 * time is a fair comparison for a five-minute window: it is the same route at the same time of
 * day, with more traffic behind the number rather than less.
 *
 * A consequence worth knowing: nothing is said about a route until there is a week of history
 * behind it.
 *
 * Not `readonly` - the weeks it reads are cached for the run, because a command asks about every
 * route in the window and they all share the same few hours.
 *
 * @see ConjunctiveThresholdPolicy for what is then done with the number
 */
final class DayOfWeekBaselineProvider implements BaselineProviderInterface
{
	/** @var array<string, array<string, PathWindowStats>> */
	private array $weeks = [];

	public function __construct(
		private readonly PerfBucketRepository $repository,
		private readonly int $lookbackWeeks,
	) {
	}

	public function baselineFor(
		Project $project,
		string $pathHash,
		PerfWindow $window,
		MetricExtractorInterface $metric,
	): ?float {
		$samples = [];

		for ($week = 1; $week <= $this->lookbackWeeks; $week++) {
			$stats = $this->week($project, $window, $week)[$pathHash] ?? null;
			if ($stats === null) {
				continue;
			}

			$value = $metric->extract($stats);
			if ($value !== null) {
				$samples[] = $value;
			}
		}

		return $samples === [] ? null : $this->median($samples);
	}

	/**
	 * The same hour of the day, that many weeks back. Subtracting whole days keeps the clock time
	 * rather than the elapsed hours, which is what makes it survive a change of daylight saving.
	 *
	 * @return array<string, PathWindowStats>
	 */
	private function week(Project $project, PerfWindow $window, int $week): array
	{
		$from = PerfGranularity::Hour->floor($window->from->sub(new DateInterval('P' . 7 * $week . 'D')));
		$key  = $project->getId() . "\x1f" . $from->format('Y-m-d H');

		return $this->weeks[$key] ??= $this->repository->aggregateByPath(
			PerfGranularity::Hour,
			$project,
			$from,
			$from->add(PerfGranularity::Hour->interval()),
		);
	}

	/** @param non-empty-list<float> $samples */
	private function median(array $samples): float
	{
		sort($samples);
		$middle = intdiv(count($samples), 2);

		return count($samples) % 2 === 1
			? $samples[$middle]
			: ($samples[$middle - 1] + $samples[$middle]) / 2;
	}
}
