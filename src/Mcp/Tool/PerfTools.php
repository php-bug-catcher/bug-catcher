<?php

namespace BugCatcher\Mcp\Tool;

use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Enum\PerfTopPathGroup;
use BugCatcher\Enum\PerfTopPathSort;
use BugCatcher\Mcp\PerfPresenter;
use BugCatcher\Mcp\ToolArguments;
use BugCatcher\Repository\ProjectRepository;
use BugCatcher\Repository\RecordPerformanceRepository;
use BugCatcher\Service\Perf\PerfSliceFilter;
use BugCatcher\Service\Perf\Report\Dto\PerfRange;
use BugCatcher\Service\Perf\Report\Dto\PerfTimePoint;
use BugCatcher\Service\Perf\Report\Dto\PerfTimeSeries;
use BugCatcher\Service\Perf\Report\Dto\TopPathRow;
use BugCatcher\Service\Perf\Report\PerfRangeResolver;
use BugCatcher\Service\Perf\Report\PerfReportBuilder;
use DateTimeImmutable;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Symfony\Component\Uid\Uuid;

/**
 * What the performance page shows, for an assistant: how a project is doing, which routes are
 * heavy, how one route behaved over time, what the database costs, and what a regression looked like.
 *
 * Read-only, and read through {@see PerfReportBuilder} alone - the same builder the page draws, so
 * a number here is the number there. Resolving a regression is `set_record_status`: a regression is
 * a record like any other.
 *
 * The window is the page's: `hours` long, starting at `at`, or ending now when `at` is left out.
 * Unlike the page an unreadable `at` is refused rather than quietly replaced by the live window -
 * an assistant cannot see that the chart moved.
 */
final class PerfTools
{
	/** The most rows one call of `get_perf_top_paths` returns. */
	public const MAX_TOP_PATHS = 100;

	/** How long back the open regressions of a project are counted, as the dashboard cell does. */
	private const REGRESSION_HOURS = 24;

	public function __construct(
		private readonly PerfReportBuilder           $report,
		private readonly ToolArguments               $arguments,
		private readonly PerfPresenter               $presenter,
		private readonly ProjectRepository           $projects,
		private readonly RecordPerformanceRepository $regressions,
		private readonly bool                        $perfEnabled,
	) {}

	/**
	 * How the projects are doing: throughput, p95 latency, Apdex and error rate over the window, and
	 * how many regressions the detector has open - the numbers of a project's dashboard row.
	 *
	 * Without `projectCode` every enabled project that has performance monitoring switched on is
	 * listed, so this is the place to start: it says which project to look at.
	 *
	 * Apdex is at T = 500 ms, rated excellent/good/fair/poor/unacceptable. `profile` "worker" is a
	 * project whose traffic is queue consumers and cron jobs, where Apdex is meaningless by design -
	 * judge it by `hits` and `openRegressions` instead.
	 *
	 * @param string|null $projectCode the `code` of a project from `list_projects`, omit for all of them
	 * @param int         $hours       length of the window in hours
	 * @param string|null $at          ISO 8601 start of the window; omit for a window ending now
	 *
	 * @return array<string, mixed>
	 */
	#[McpTool(name: 'get_perf_health')]
	public function getPerfHealth(
		?string $projectCode = null,
		#[Schema(minimum: 1, maximum: PerfRangeResolver::MAX_HOURS)]
		int     $hours = PerfRangeResolver::DEFAULT_HOURS,
		?string $at = null,
	): array {
		$this->assertEnabled();
		$range    = $this->arguments->window($at, $hours);
		$projects = $projectCode === null
			? $this->projects->findBy(['enabled' => true, 'perfEnabled' => true], ['code' => 'ASC'])
			: [$this->arguments->project($projectCode)];
		$since    = new DateTimeImmutable(sprintf('-%d hours', self::REGRESSION_HOURS));

		$rows   = [];
		$window = null;
		foreach ($projects as $project) {
			$health = $this->report->health($project, $range->from, $range->to);
			$window ??= $health->window;

			$rows[] = [
					'projectCode' => $project->getCode(),
					'perfEnabled' => $project->isPerfEnabled(),
					'profile'     => $this->profile($project),
				] + $this->presenter->health($health) + [
					'openRegressions' => $this->regressions->countOpenSince($project, $since),
				];
		}

		return [
			'window'   => $window === null ? $this->rangeOnly($range) : $this->presenter->window($window),
			'projects' => $rows,
			'note'     => sprintf(
				'openRegressions counts unresolved regressions of the last %d hours, whatever the window. '
				. 'List them with search_records type "perf".',
				self::REGRESSION_HOURS,
			),
		];
	}

	/**
	 * The heaviest routes of the window - the table on the performance page - or the vhosts or
	 * machines, with `groupBy`.
	 *
	 * Use `pathContains` to find a route or script by part of its path ("checkout", "cron.php").
	 * Every row is both per request (`avgMs`, `p95Ms`, `userCpuMs`, `waitMs`, `memPerHitBytes`, ...)
	 * and in total (`totalMs`). `database` is null where nothing counted queries. Without a project
	 * the label starts with the project code, since two applications both have a `/login`.
	 *
	 * The limit is applied in SQL by the chosen sort, so sorting by `max_time` does find a slow route
	 * called once an hour. Except `p95`, which no query can order by: that is the slowest of the
	 * busiest rows.
	 *
	 * @param string|null $projectCode  the `code` of a project from `list_projects`, omit for all of them
	 * @param int         $hours        length of the window in hours
	 * @param string|null $at           ISO 8601 start of the window; omit for a window ending now
	 * @param string      $groupBy      "path" for routes, "host" for vhosts, "server" for machines
	 * @param string      $sortBy       hits, total (time), user / sys (CPU), mem (per hit), max_mem, max_time (slowest run), p95
	 * @param int         $limit        rows to return
	 * @param string|null $pathContains only routes whose path contains this text, case-insensitive
	 * @param string|null $host         only this vhost, as a row grouped by "host" names it
	 * @param string|null $server       only this machine, as a row grouped by "server" names it
	 *
	 * @return array<string, mixed>
	 */
	#[McpTool(name: 'get_perf_top_paths')]
	public function getPerfTopPaths(
		?string $projectCode = null,
		#[Schema(minimum: 1, maximum: PerfRangeResolver::MAX_HOURS)]
		int     $hours = PerfRangeResolver::DEFAULT_HOURS,
		?string $at = null,
		#[Schema(enum: ['path', 'host', 'server'])]
		string  $groupBy = 'path',
		#[Schema(enum: ['hits', 'total', 'user', 'sys', 'mem', 'max_mem', 'max_time', 'p95'])]
		string  $sortBy = 'hits',
		#[Schema(minimum: 1, maximum: self::MAX_TOP_PATHS)]
		int     $limit = PerfReportBuilder::DEFAULT_TOP_PATHS,
		?string $pathContains = null,
		?string $host = null,
		?string $server = null,
	): array {
		$this->assertEnabled();
		$project = $this->arguments->optionalProject($projectCode);
		$range   = $this->arguments->window($at, $hours);
		$group   = $this->enum(PerfTopPathGroup::class, $groupBy, 'groupBy');
		$sort    = $this->enum(PerfTopPathSort::class, $sortBy, 'sortBy');

		if ($limit < 1 || $limit > self::MAX_TOP_PATHS) {
			throw new ToolCallException(sprintf('"limit" has to be between 1 and %d.', self::MAX_TOP_PATHS));
		}

		$report = $this->report->topPaths(
			$project,
			$range->from,
			$range->to,
			$group,
			$sort,
			$limit,
			new PerfSliceFilter(pathContains: $pathContains, host: $host, serverName: $server),
		);

		return [
			'window'    => $this->presenter->window($report->window),
			'groupBy'   => $report->group->value,
			'sortBy'    => $report->sort->value,
			// more rows matched than were returned
			'truncated' => $report->truncated,
			'rows'      => array_map($this->presenter->row(...), $report->rows),
			'note'      => $report->isEmpty() ? $this->presenter->emptyNote($report->window) : null,
		];
	}

	/**
	 * One route over the window, in full: latency (avg, p95, slowest run, Apdex), where the time went
	 * (user CPU, system CPU, waiting), memory, the status mix, latency bands, the database, and the
	 * same numbers per machine and per vhost.
	 *
	 * `path` is matched exactly, the way it appears as a `label` of `get_perf_top_paths` grouped by
	 * path for one project. Find it there with `pathContains` first.
	 *
	 * @param string      $projectCode the `code` of a project from `list_projects`
	 * @param string      $path        the exact route
	 * @param int         $hours       length of the window in hours
	 * @param string|null $at          ISO 8601 start of the window; omit for a window ending now
	 *
	 * @return array<string, mixed>
	 */
	#[McpTool(name: 'get_perf_path')]
	public function getPerfPath(
		string  $projectCode,
		string  $path,
		#[Schema(minimum: 1, maximum: PerfRangeResolver::MAX_HOURS)]
		int     $hours = PerfRangeResolver::DEFAULT_HOURS,
		?string $at = null,
	): array {
		$this->assertEnabled();
		$project = $this->arguments->project($projectCode);
		$range   = $this->arguments->window($at, $hours);
		$filter  = PerfSliceFilter::path($path);
		$summary = $this->report->summary($project, $range->from, $range->to, $filter);

		if ($summary->isEmpty()) {
			return [
				'window'      => $this->presenter->window($summary->window),
				'projectCode' => $project->getCode(),
				'path'        => $path,
				'summary'     => null,
				'note'        => trim(sprintf(
					'No traffic on exactly this path in the window. Look it up with get_perf_top_paths '
					. 'and pathContains - the path has to match a label there character for character. %s',
					$this->presenter->emptyNote($summary->window),
				)),
			];
		}

		$slices = fn(PerfTopPathGroup $group): array => array_map(
			$this->presenter->row(...),
			$this->report->topPaths(
				$project,
				$range->from,
				$range->to,
				$group,
				PerfTopPathSort::Hits,
				PerfReportBuilder::DEFAULT_TOP_PATHS,
				$filter,
			)->rows,
		);

		return [
			'window'      => $this->presenter->window($summary->window),
			'projectCode' => $project->getCode(),
			'path'        => $path,
			'summary'     => $this->presenter->summary($summary),
			'byServer'    => $slices(PerfTopPathGroup::Server),
			'byHost'      => $slices(PerfTopPathGroup::Host),
		];
	}

	/**
	 * The window bucket by bucket - the charts of the performance page as numbers: throughput,
	 * latency, CPU and waiting, memory, status mix, latency bands and the database, per bucket.
	 *
	 * Buckets are minutes up to two hours, hours up to seven days, days beyond. Only buckets that
	 * had traffic are listed; a bucket that is missing had none.
	 *
	 * @param string|null $projectCode the `code` of a project from `list_projects`, omit for all of them
	 * @param string|null $path        one route, exactly as `get_perf_top_paths` labels it; omit for every route
	 * @param int         $hours       length of the window in hours
	 * @param string|null $at          ISO 8601 start of the window; omit for a window ending now
	 * @param string|null $host        only this vhost
	 * @param string|null $server      only this machine
	 *
	 * @return array<string, mixed>
	 */
	#[McpTool(name: 'get_perf_timeseries')]
	public function getPerfTimeseries(
		?string $projectCode = null,
		?string $path = null,
		#[Schema(minimum: 1, maximum: PerfRangeResolver::MAX_HOURS)]
		int     $hours = PerfRangeResolver::DEFAULT_HOURS,
		?string $at = null,
		?string $host = null,
		?string $server = null,
	): array {
		$this->assertEnabled();
		$project = $this->arguments->optionalProject($projectCode);
		$range   = $this->arguments->window($at, $hours);
		$series  = $this->report->timeSeries(
			$project,
			$range->from,
			$range->to,
			$path,
			new PerfSliceFilter(host: $host, serverName: $server),
		);

		return $this->series($series);
	}

	/**
	 * What the database costs: queries per request, milliseconds per request, the share of the
	 * response that was the database, and the routes that spend the most time there.
	 *
	 * `collected` false with traffic in the window means the monitored application does not have
	 * php-bug-catcher/perf-collector-bundle installed - the hook alone cannot see a query.
	 *
	 * The routes are the heaviest of the twenty busiest, the way the page lists them; they ignore
	 * `path`.
	 *
	 * @param string|null $projectCode the `code` of a project from `list_projects`, omit for all of them
	 * @param string|null $path        one route for the totals, exactly as `get_perf_top_paths` labels it
	 * @param int         $hours       length of the window in hours
	 * @param string|null $at          ISO 8601 start of the window; omit for a window ending now
	 *
	 * @return array<string, mixed>
	 */
	#[McpTool(name: 'get_perf_database')]
	public function getPerfDatabase(
		?string $projectCode = null,
		?string $path = null,
		#[Schema(minimum: 1, maximum: PerfRangeResolver::MAX_HOURS)]
		int     $hours = PerfRangeResolver::DEFAULT_HOURS,
		?string $at = null,
	): array {
		$this->assertEnabled();
		$project  = $this->arguments->optionalProject($projectCode);
		$range    = $this->arguments->window($at, $hours);
		$database = $this->report->database($project, $range->from, $range->to, $path);
		$window   = $database->series->window;

		$answer = [
			'window'    => $this->presenter->window($window),
			'hits'      => $database->series->hits(),
			'collected' => $database->isCollected(),
		];

		if ($database->isEmpty()) {
			return $answer + ['note' => $this->presenter->emptyNote($window) ?? 'No traffic in the window.'];
		}

		if (!$database->isCollected()) {
			return $answer + [
					'note' => 'The window has traffic but no query counts: install php-bug-catcher/perf-collector-bundle '
						. 'in the monitored application.',
				];
		}

		return $answer + [
				'queries'         => $this->presenter->round($database->queries()),
				'queriesPerHit'   => $this->presenter->round($database->queriesPerHit()),
				'dbMsPerHit'      => $this->presenter->round($database->dbMsPerHit()),
				'shareOfResponse' => $this->presenter->ratio($database->shareOfWallclock()),
				'routes'          => array_map(fn(TopPathRow $row): array => [
					'label'           => $row->label,
					'hits'            => $row->hits,
					'queriesPerHit'   => $this->presenter->round($database::queriesPerHitOf($row)),
					'dbMsPerHit'      => $this->presenter->round($database::dbMsPerHitOf($row)),
					'shareOfResponse' => $this->presenter->ratio($database::shareOf($row)),
				], $database->routes),
			];
	}

	/**
	 * A performance regression with its route around the time it was found - minutes either side if
	 * they are still kept, hours otherwise. The detail chart of a regression, as numbers.
	 *
	 * `baseline` and `observed` are in `unit`: ms, bytes or a ratio.
	 *
	 * @param string $recordId the `id` of an entry `search_records` returned with type "perf"
	 *
	 * @return array<string, mixed>
	 */
	#[McpTool(name: 'get_perf_regression')]
	public function getPerfRegression(string $recordId): array {
		$this->assertEnabled();

		$record = Uuid::isValid($recordId) ? $this->regressions->find(Uuid::fromString($recordId)) : null;
		if (!$record instanceof RecordPerformance) {
			throw new ToolCallException(sprintf(
				'There is no performance regression with the id "%s". Find one with search_records and type "perf".',
				$recordId,
			));
		}

		$detail = $this->report->pathDetail($record);

		return [
			'id'          => $recordId,
			'projectCode' => $record->getProject()?->getCode(),
			'status'      => $record->getStatus(),
			'message'     => $record->getMessage(),
			'path'        => $detail->path,
			'metric'      => $detail->metric,
			'unit'        => $detail->unit->value,
			'baseline'    => $detail->baseline,
			'observed'    => $detail->observed,
			'windowAt'    => $detail->windowAt->format(PerfPresenter::DATE_FORMAT),
		] + $this->series($detail->series);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function series(PerfTimeSeries $series): array {
		$points = array_values(array_filter($series->points, static fn(PerfTimePoint $point): bool => $point->hits > 0));

		return [
			'window' => $this->presenter->window($series->window),
			'hits'   => $series->hits(),
			'points' => array_map($this->presenter->point(...), $points),
			'note'   => $points === [] ? $this->presenter->emptyNote($series->window) : null,
		];
	}

	/**
	 * The window as it was asked for, for an answer about no project at all - there is no report to
	 * say how it was rounded to buckets.
	 *
	 * @return array{from: string, to: string, granularity: null}
	 */
	private function rangeOnly(PerfRange $range): array {
		return [
			'from'        => $range->from->format(PerfPresenter::DATE_FORMAT),
			'to'          => $range->to->format(PerfPresenter::DATE_FORMAT),
			'granularity' => null,
		];
	}

	private function profile(Project $project): ?string {
		if (!$project->isPerfEnabled()) {
			return null;
		}

		return $project->getPerfProfile()->value;
	}

	/**
	 * @template T of \BackedEnum
	 * @param class-string<T> $enum
	 * @return T
	 */
	private function enum(string $enum, string $value, string $argument): \BackedEnum {
		return $enum::tryFrom($value) ?? throw new ToolCallException(sprintf(
			'"%s" is not a value of "%s". Use one of: %s.',
			$value, $argument, implode(', ', array_map(static fn(\BackedEnum $case) => $case->value, $enum::cases())),
		));
	}

	private function assertEnabled(): void {
		if (!$this->perfEnabled) {
			throw new ToolCallException(
				'Performance monitoring is off on this instance (bug_catcher.perf.enabled), so there is nothing to report.',
			);
		}
	}
}
