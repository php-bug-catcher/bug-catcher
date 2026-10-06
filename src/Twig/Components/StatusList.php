<?php

namespace BugCatcher\Twig\Components;

use BugCatcher\Controller\AbstractController;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\Role;
use BugCatcher\Enum\PerfProfile;
use BugCatcher\Repository\ProjectRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsLiveComponent]
final class StatusList extends AbstractController {
	use DefaultActionTrait;
	public string $status = 'new';

	/**
	 * @param list<string> $components       the row of a project that only reports errors
	 * @param list<string> $perfComponents   the row of a web project that also reports latency
	 * @param list<string> $workerComponents the row of a project whose work is cron jobs and
	 *                                       loops, where latency is not a measure of anything
	 * @param bool         $perfEnabled      `bug_catcher.perf.enabled`, the installation-wide switch
	 */
	public function __construct(
		private readonly ProjectRepository $projectRepo,
		public readonly array              $components,
		public readonly array              $perfComponents,
		public readonly array              $workerComponents,
		private readonly bool              $perfEnabled,
	) {}

	public function getProjects(): array {

		return $this->getUser()->getActiveProjects()->toArray();
	}

	/**
	 * Whether this project's row talks about latency as well as errors.
	 *
	 * Both switches have to be on. The per-project one is the administrator saying "the collector
	 * runs on this machine"; the installation-wide one is `bug_catcher.perf.enabled`, and with it
	 * off there is nothing in `perf_bucket` to read - a row of dashes is worse than the row the
	 * dashboard had before.
	 */
	public function usesPerf(Project $project): bool {
		return $this->perfEnabled && $project->isPerfEnabled();
	}

	/**
	 * Which cells this project's row is made of.
	 *
	 * Two decisions, in this order. {@see usesPerf()} says whether there is a performance row at
	 * all - that part is unchanged, and a project with the collector switched off keeps the error
	 * row whatever its workload is. Only then does the workload pick between the two: a cron box
	 * has nobody waiting on it, so Apdex and p95 are replaced by the two cells that can say
	 * something about a job - see {@see PerfProfile}.
	 *
	 * @return list<string>
	 */
	public function componentsFor(Project $project): array {
		if (!$this->usesPerf($project)) {
			return $this->components;
		}

		return match ($project->getPerfProfile()) {
			PerfProfile::Web    => $this->perfComponents,
			PerfProfile::Worker => $this->workerComponents,
		};
	}

	/**
	 * Whether the whole wall is drawn on the tighter grid - **not** a property of one row.
	 *
	 * All three lists share twelve columns but cut them differently: with performance on the row
	 * the name gives up two columns and the error count one, to make room for the two numbers -
	 * Apdex and p95 on a web row, a regression count and a rate on a worker one. Deciding that
	 * per project means the cards sit side by side with their numbers at different offsets, and a
	 * wall of projects is read by scanning down a column, not by reading each card.
	 *
	 * So one project with performance switched on puts every row on the same grid; the ones
	 * without it simply have a wider sparkline where the two numbers would be. An installation
	 * with no performance anywhere is untouched - the old six/two/four.
	 */
	public function isDense(): bool {
		foreach ($this->getProjects() as $project) {
			if ($this->usesPerf($project)) {
				return true;
			}
		}

		return false;
	}
}
