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
}
