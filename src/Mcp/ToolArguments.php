<?php

namespace BugCatcher\Mcp;

use BugCatcher\Entity\Project;
use BugCatcher\Repository\ProjectRepository;
use BugCatcher\Service\Perf\Report\Dto\PerfRange;
use BugCatcher\Service\Perf\Report\PerfRangeResolver;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Mcp\Exception\ToolCallException;

/**
 * Turning what an MCP client typed into what the bundle reads, for every tool alike.
 *
 * Each refusal is a {@see ToolCallException} whose message says what to send instead: that message
 * is all a caller is ever told, and an assistant reading "Error while executing tool" has nothing to
 * correct.
 */
final readonly class ToolArguments
{
	public function __construct(private ProjectRepository $projects) {}

	public function project(string $code): Project {
		$project = $this->projects->findOneBy(['code' => $code]);
		if ($project === null) {
			throw new ToolCallException(sprintf(
				'There is no project with the code "%s". Call list_projects for the known ones.', $code,
			));
		}

		return $project;
	}

	public function optionalProject(?string $code): ?Project {
		return $code === null ? null : $this->project($code);
	}

	/**
	 * Moved into the server's own timezone: the columns carry none, so a value is compared as the
	 * wall clock it prints - and `2026-01-31T09:00:00Z` printed on a server in Bratislava is ten
	 * o'clock, not nine.
	 */
	public function date(?string $value, string $argument): ?DateTimeImmutable {
		if ($value === null) {
			return null;
		}

		try {
			return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone(date_default_timezone_get()));
		} catch (Exception $e) {
			throw new ToolCallException(sprintf(
				'"%s" is not a date I can read as "%s". Use an ISO 8601 value such as "2026-01-31T23:59:59".',
				$value, $argument,
			), previous: $e);
		}
	}

	/**
	 * `[at, at + hours)`, or the last `hours` up to now when `at` is left out - what `?at=&hours=`
	 * means on the performance page.
	 *
	 * Not {@see PerfRangeResolver::resolve()}, which reads a value out of a URL and so falls back to
	 * the live window on anything it cannot parse: a person sees the page change and corrects it, an
	 * assistant would report on the wrong hour without knowing. The clamp is the same one, for the
	 * same reason - the answer to a year of minutes is a lot of JSON.
	 */
	public function window(?string $at, int $hours): PerfRange {
		$hours  = max(1, min(PerfRangeResolver::MAX_HOURS, $hours));
		$length = new DateInterval(sprintf('PT%dH', $hours));
		$from   = $this->date($at, 'at');

		if ($from === null) {
			$to = new DateTimeImmutable();

			return new PerfRange($to->sub($length), $to, true);
		}

		return new PerfRange($from, $from->add($length), false);
	}
}
