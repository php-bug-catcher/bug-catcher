<?php

declare(strict_types=1);

namespace BugCatcher\Controller;

use BugCatcher\Entity\Project;
use BugCatcher\Repository\ProjectRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * The performance page: one project's charts, or every project's at once.
 *
 * Deliberately not rows on the dashboard. The dashboard answers one question from across the
 * room - is anything on fire - and four charts of one project is a different question, asked by
 * somebody who has already picked a project out of that wall. Giving it a URL also makes it
 * linkable, which a Live Component panel buried in a list of rows never was.
 *
 * The panels themselves are unchanged and still take a `project` of null as "all of them"; what
 * this adds is the two ways in - the project name on the dashboard, and the button in the nav.
 */
final class PerformanceController extends AbstractController
{
	/**
	 * The panels that belong here and nowhere else.
	 *
	 * Named so that {@see \BugCatcher\BugCatcherBundle::loadExtension()} can refuse them in
	 * `dashboard_components`: both lists are resolved by component name, so a leftover entry
	 * would keep drawing them on the homepage without anything going wrong.
	 */
	public const array PANELS = ['PerfOverview', 'PerfTopPaths', 'PerfDatabase'];

	/** @param list<string> $components the panels of the page, from bug_catcher.performance_components */
	public function __construct(
		private readonly array $components,
		private readonly int $refreshInterval,
		private readonly ProjectRepository $projects,
	) {
	}

	public function index(?Project $project = null): Response
	{
		// a uuid in the URL is a guess anybody can make, and these charts are the shape of an
		// application's traffic. 404 rather than 403: whether a project exists is itself an
		// answer somebody did not ask for
		if ($project !== null && !$this->isVisible($project)) {
			throw $this->createNotFoundException();
		}

		return $this->render('@BugCatcher/dashboard/performance.html.twig', [
			'project'         => $project,
			'projects'        => $this->visibleProjects(),
			'components'      => $this->components,
			'refreshInterval' => $this->refreshInterval,
		]);
	}

	private function isVisible(Project $project): bool
	{
		$user = $this->getUser();

		// an installation that put no firewall over this page made that choice itself; it is not
		// a reason to answer "no such project" to everybody
		return $user === null || $user->getActiveProjects()->contains($project);
	}

	/** @return list<Project> what the switcher at the top of the page offers */
	private function visibleProjects(): array
	{
		$user = $this->getUser();

		return $user === null
			? $this->projects->findBy(['enabled' => true], ['name' => 'ASC'])
			: array_values($user->getActiveProjects()->toArray());
	}
}
