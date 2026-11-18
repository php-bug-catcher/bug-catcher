<?php

namespace BugCatcher\Mcp\Tool;

use BugCatcher\Entity\Project;
use BugCatcher\Repository\ProjectRepository;
use Mcp\Capability\Attribute\McpTool;

/**
 * The entry point of every MCP session: everything else is filtered by a project code, and this is
 * where the client learns which codes exist.
 */
final class ProjectTools
{
	public function __construct(private readonly ProjectRepository $projects) {}

	/**
	 * Lists the projects Bug Catcher collects errors for.
	 *
	 * Use the returned `code` as the `projectCode` argument of the other tools. `perfEnabled` says
	 * whether the project ships performance data for the `get_perf_*` tools, and `perfProfile`
	 * whether it serves web requests ("web") or runs queue consumers and cron jobs ("worker"); it
	 * is null when performance monitoring is off for the project.
	 *
	 * @return array<int, array{code: string|null, name: string|null, url: string|null, perfEnabled: bool, perfProfile: string|null}>
	 */
	#[McpTool(name: 'list_projects')]
	public function listProjects(): array {
		// disabled projects are the ones nobody reports to any more - listing them would send the
		// reader searching for errors that cannot arrive
		$projects = $this->projects->findBy(['enabled' => true], ['code' => 'ASC']);

		return array_map(fn(Project $project) => [
			'code'        => $project->getCode(),
			'name'        => $project->getName(),
			'url'         => $project->getUrl(),
			'perfEnabled' => $project->isPerfEnabled(),
			'perfProfile' => $project->isPerfEnabled() ? $project->getPerfProfile()->value : null,
		], $projects);
	}
}
