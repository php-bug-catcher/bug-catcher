<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection;

use BugCatcher\Entity\Project;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\PerfWindow;
use Psr\Log\LoggerInterface;

/**
 * Every enabled detector over every project that measured something, with what they find written
 * down as records.
 *
 * Projects are not configured anywhere - a collector is installed on a machine and starts
 * shipping - so the window itself says which projects there are to look at. A project somebody
 * switched off is skipped: it is still in the table, and its buckets are still arriving until the
 * collector is stopped, but nobody is watching it.
 */
final readonly class DetectionRunner
{
	/** @param list<PerfDetectorInterface> $detectors the ones `perf.detectors` switched on */
	public function __construct(
		private PerfBucketRepository $repository,
		private RecordPerformanceWriter $writer,
		private array $detectors,
		private LoggerInterface $logger,
	) {
	}

	public function run(PerfWindow $window): DetectionResult
	{
		$projects = array_values(array_filter(
			$this->repository->projectsWithBuckets($window->granularity, $window->from, $window->to),
			static fn(Project $project): bool => $project->isEnabled() === true,
		));

		$findings = [];
		foreach ($projects as $project) {
			foreach ($this->detectors as $detector) {
				foreach ($detector->detect($project, $window) as $finding) {
					$findings[] = $finding;
					$this->report($detector, $finding);
				}
			}
		}

		$this->writer->write($findings);

		return new DetectionResult(count($projects), count($findings));
	}

	private function report(PerfDetectorInterface $detector, AnomalyFinding $finding): void
	{
		$this->logger->notice('Performance regression on {path}: {metric} {baseline} -> {observed} ({detector}).', [
			'path'     => $finding->path,
			'metric'   => $finding->metric,
			'baseline' => $finding->unit->format($finding->baseline),
			'observed' => $finding->unit->format($finding->observed),
			'detector' => $detector->name(),
			'project'  => $finding->project->getCode(),
		]);
	}
}
