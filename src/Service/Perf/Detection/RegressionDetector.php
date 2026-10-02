<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\PerfWindow;

/**
 * The detector the bundle ships: one configured metric, compared against what the same route did
 * at the same time of day on previous same weekdays.
 *
 * It is assembled out of three interfaces on purpose - what to measure
 * ({@see MetricExtractorInterface}), what normal is ({@see BaselineProviderInterface}) and when to
 * care ({@see AnomalyPolicyInterface}) - so an installation can replace any one of them without
 * writing a detector.
 */
final readonly class RegressionDetector implements PerfDetectorInterface
{
	public const string NAME = 'regression';

	public function __construct(
		private PerfBucketRepository $repository,
		private MetricExtractorRegistry $metrics,
		private BaselineProviderInterface $baselines,
		private AnomalyPolicyInterface $policy,
		private string $metricName,
	) {
	}

	public function name(): string
	{
		return self::NAME;
	}

	/** @return iterable<AnomalyFinding> */
	public function detect(Project $project, PerfWindow $window): iterable
	{
		$metric = $this->metrics->get($this->metricName);
		$routes = $this->repository->aggregateByPath(
			$window->granularity,
			$project,
			$window->from,
			$window->to,
		);

		foreach ($routes as $route) {
			// the roll-up's overflow row is not a route: "everything else got slower" is nothing
			// anybody can open, and the fix for seeing it is a normalisation rule
			if ($route->label === PerfBucket::OTHER_PATH) {
				continue;
			}

			$observed = $metric->extract($route);
			if ($observed === null) {
				continue;
			}

			$baseline = $this->baselines->baselineFor($project, $route->key, $window, $metric);
			if ($baseline === null) {
				continue;
			}

			if (!$this->policy->isAnomalous($baseline, $observed, $route->hits, $metric->unit())) {
				continue;
			}

			yield new AnomalyFinding(
				$project,
				$route->label,
				$metric->name(),
				$metric->unit(),
				$baseline,
				$observed,
				$window->from,
			);
		}
	}
}
