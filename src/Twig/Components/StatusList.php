<?php

namespace BugCatcher\Twig\Components;

use BugCatcher\Controller\AbstractController;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\Role;
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
	 * @param list<string> $components     the row of a project that only reports errors
	 * @param list<string> $perfComponents the row of a project that also reports latency
	 * @param bool         $perfEnabled    `bug_catcher.perf.enabled`, the installation-wide switch
	 */
	public function __construct(
		private readonly ProjectRepository $projectRepo,
		public readonly array              $components,
		public readonly array              $perfComponents,
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

	/** @return list<string> */
	public function componentsFor(Project $project): array {
		return $this->usesPerf($project) ? $this->perfComponents : $this->components;
	}

	/**
	 * Whether the whole wall is drawn on the tighter grid - **not** a property of one row.
	 *
	 * The two lists share twelve columns but cut them differently: with latency on the row the
	 * name gives up two columns and the error count one, to make room for Apdex and p95. Deciding
	 * that per project means the cards sit side by side with their numbers at different offsets,
	 * and a wall of projects is read by scanning down a column, not by reading each card.
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
