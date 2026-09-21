<?php

namespace BugCatcher\Mcp\Tool;

use BugCatcher\Entity\Project;
use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordLogTrace;
use BugCatcher\Mcp\HasMcpDetails;
use BugCatcher\Mcp\RecordFinder;
use BugCatcher\Mcp\RecordSearchCriteria;
use BugCatcher\Mcp\RecordTypes;
use BugCatcher\Mcp\StackTraceFormatter;
use BugCatcher\Repository\ProjectRepository;
use BugCatcher\Repository\RecordRepositoryInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use InvalidArgumentException;
use LogicException;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Symfony\Component\Uid\Uuid;

/**
 * Reading and resolving the collected errors.
 *
 * Which record types are readable is per instance - an application adds its own, and lists them in
 * `bug_catcher.mcp.record_types` - so every entry reports the `type` it is, and the error message for
 * an unknown `type` argument names the ones this instance does have. That message is the only place a
 * caller can learn them, because the set cannot go in a schema fixed at build time.
 *
 * Every failure leaves here as a {@see ToolCallException}: that is the one exception the SDK turns
 * into an error result carrying its message, so it is the only way the caller is told what went
 * wrong. Anything else becomes a bare "Error while executing tool".
 */
final class RecordTools
{
	/**
	 * The statuses a caller may set.
	 *
	 * Not a nicety: {@see \BugCatcher\Repository\RecordRepository::getUpdateStatusQB()} interpolates
	 * the new status straight into DQL, so an unchecked value here is an injection into the update.
	 */
	private const SETTABLE_STATUSES = ['resolved', 'archived'];

	/**
	 * Carries no timezone because the stored column carries none either - naming one would be
	 * inventing it. These are the same wall clock times the dashboard shows.
	 */
	private const DATE_FORMAT = 'Y-m-d H:i:s';

	public function __construct(
		private readonly RecordFinder        $finder,
		private readonly ProjectRepository   $projects,
		private readonly StackTraceFormatter $stackTraces,
		private readonly ManagerRegistry     $registry,
		private readonly EntityManagerInterface $em,
		private readonly RecordTypes         $types,
	) {}

	/**
	 * Finds errors collected from the client applications, one entry per distinct error.
	 *
	 * Records repeat, so occurrences of one error are collapsed into a single entry: `count` is how
	 * often it happened in the range, `date` is the latest occurrence and `firstOccurrence` the
	 * earliest. Pass the `id` of an entry to `get_record_detail` for the stack trace.
	 *
	 * Not every entry is a PHP exception. Each one carries the `type` of record it is - "log" and
	 * "trace-log" are reports from the client application, and an instance can add its own, such as
	 * "cron" for a scheduled command that did not finish. A type that carries no monolog level reports
	 * `level` null, and the `message` of such a record is worked out from its own fields.
	 *
	 * @param string|null $projectCode the `code` of a project from `list_projects`, omit to search all
	 * @param string      $status      matched as a prefix: "new" is unresolved, "resolved" and "archived" are dealt with
	 * @param string|null $code        the error code carried by the record
	 * @param int|null    $minLevel    monolog level floor: 200 info, 300 warning, 400 error, 500 critical. Only records that carry a level at all, so a type without one is left out when this is given
	 * @param string|null $dateFrom    ISO 8601, inclusive
	 * @param string|null $dateTo      ISO 8601, inclusive
	 * @param int         $limit       number of distinct errors to return
	 * @param string|null $type        only records of this type, as reported in the `type` field; omit for every type. An unknown value answers with the ones this instance searches
	 *
	 * @return array<int, array<string, mixed>>
	 */
	#[McpTool(name: 'search_records')]
	public function searchRecords(
		?string $projectCode = null,
		string  $status = RecordSearchCriteria::DEFAULT_STATUS,
		?string $code = null,
		#[Schema(minimum: 0)]
		?int    $minLevel = null,
		?string $dateFrom = null,
		?string $dateTo = null,
		#[Schema(minimum: 1, maximum: RecordSearchCriteria::MAX_LIMIT)]
		int     $limit = RecordSearchCriteria::DEFAULT_LIMIT,
		?string $type = null,
	): array {
		$criteria = $this->criteria($projectCode, $status, $code, $minLevel, $dateFrom, $dateTo, $limit, $type);

		return array_map($this->summarise(...), $this->finder->search($criteria));
	}

	/**
	 * Reads one error in full, with its stack trace and the history of its occurrences.
	 *
	 * Worth doing before `set_record_status`: resolving an error may drop its stack trace, and the
	 * trace is the part that cannot be recovered afterwards.
	 *
	 * `details` carries whatever the record type knows beyond the fields every record has - for a cron
	 * run, the command, its timings and how they compare to what was expected. It is null for a type
	 * that has nothing to add.
	 *
	 * @param string $recordId the `id` of an entry returned by `search_records`
	 *
	 * @return array<string, mixed>
	 */
	#[McpTool(name: 'get_record_detail')]
	public function getRecordDetail(string $recordId): array {
		$record = $this->record($recordId);

		$history = array_map(fn(Record $occurrence) => [
			'date'  => $occurrence->getDate()->format(self::DATE_FORMAT),
			'count' => $occurrence->getCount(),
		], $this->finder->history($record));

		return $this->summarise($record) + [
				'metadata'   => $record->metadata,
				'stackTrace' => $this->stackTraces->format(
					$record instanceof RecordLogTrace ? $record->getStackTrace() : null,
				),
				// the key is always there, the way stackTrace is: a missing key reads as "the tool does
				// not report this", a null one as "this record has none"
				'details'    => $record instanceof HasMcpDetails ? $record->getMcpDetails() : null,
				'history'    => $history,
			];
	}

	/**
	 * Marks an error as dealt with, together with every other occurrence of it.
	 *
	 * `resolved` means the cause was fixed, `archived` means it needs no fixing. The whole group is
	 * cleared either way, the same as the buttons on the dashboard do.
	 *
	 * Careful: resolving may clear the stack trace, depending on `clear_stacktrace_on_fixed`. Read
	 * the detail first if the trace still matters.
	 *
	 * @param string $recordId the `id` of an entry returned by `search_records`
	 * @param string $status   `resolved` or `archived`
	 *
	 * @return array<string, mixed>
	 */
	#[McpTool(name: 'set_record_status')]
	public function setRecordStatus(
		string $recordId,
		#[Schema(enum: self::SETTABLE_STATUSES)]
		string $status,
	): array {
		if (!in_array($status, self::SETTABLE_STATUSES, true)) {
			throw new ToolCallException(sprintf(
				'Cannot set the status to "%s", it has to be one of: %s.',
				$status, implode(', ', self::SETTABLE_STATUSES),
			));
		}

		$record   = $this->record($recordId);
		$previous = $record->getStatus();
		$repo     = $this->registry->getRepository($record::class);
		if (!$repo instanceof RecordRepositoryInterface) {
			throw new LogicException(sprintf(
				'Repository of "%s" has to implement "%s".', $record::class, RecordRepositoryInterface::class,
			));
		}

		// the group is cleared from its first occurrence on, the way RecordStatusController does it
		$oldest = $repo->findBy(
				['hash' => $record->getHash(), 'status' => $previous],
				['date' => 'ASC'],
				1,
			)[0] ?? $record;

		$repo->setStatus($record, $oldest->getDate(), $status, $previous, true);

		return [
			'id'             => $recordId,
			'status'         => $status,
			'previousStatus' => $previous,
		];
	}

	private function criteria(
		?string $projectCode,
		string  $status,
		?string $code,
		?int    $minLevel,
		?string $dateFrom,
		?string $dateTo,
		int     $limit,
		?string $type = null,
	): RecordSearchCriteria {
		try {
			return new RecordSearchCriteria(
				$projectCode === null ? null : $this->project($projectCode),
				$status,
				$code,
				$minLevel,
				$this->date($dateFrom, 'dateFrom'),
				$this->date($dateTo, 'dateTo'),
				$limit,
				$this->type($type),
			);
		} catch (InvalidArgumentException $e) {
			// the value objects's own rules - limit out of range, range ending before it starts
			throw new ToolCallException($e->getMessage(), previous: $e);
		}
	}

	private function project(string $code): Project {
		$project = $this->projects->findOneBy(['code' => $code]);
		if ($project === null) {
			throw new ToolCallException(sprintf(
				'There is no project with the code "%s". Call list_projects for the known ones.', $code,
			));
		}

		return $project;
	}

	/**
	 * A discriminator value this server actually searches.
	 *
	 * The valid set comes from `bug_catcher.mcp.record_types`, so it cannot be an enum in the argument
	 * schema - this message is where a caller learns what there is.
	 */
	private function type(?string $type): ?string {
		if ($type === null || in_array($type, $this->types->discriminators(), true)) {
			return $type;
		}

		throw new ToolCallException(sprintf(
			'There is no searchable record type "%s". This server searches: %s.',
			$type, implode(', ', $this->types->discriminators()),
		));
	}

	private function date(?string $value, string $argument): ?DateTimeImmutable {
		if ($value === null) {
			return null;
		}

		try {
			return new DateTimeImmutable($value);
		} catch (Exception $e) {
			throw new ToolCallException(sprintf(
				'"%s" is not a date I can read as "%s". Use an ISO 8601 value such as "2026-01-31T23:59:59".',
				$value, $argument,
			), previous: $e);
		}
	}

	private function record(string $recordId): Record {
		if (!Uuid::isValid($recordId)) {
			throw new ToolCallException(sprintf(
				'"%s" is not a record id. Use the "id" of an entry returned by search_records.', $recordId,
			));
		}

		$record = $this->finder->find(Uuid::fromString($recordId));
		if ($record === null) {
			throw new ToolCallException(sprintf('There is no record with the id "%s".', $recordId));
		}

		return $record;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function summarise(Record $record): array {
		return [
			'id'              => (string)$record->getId(),
			'projectCode'     => $record->getProject()?->getCode(),
			'type'            => $this->discriminator($record),
			'status'          => $record->getStatus(),
			'level'           => $record->getLevel(),
			'message'         => $record->getMessage(),
			'code'            => $record->getCode(),
			'requestUri'      => $record->getRequestUri(),
			'date'            => $record->getDate()?->format(self::DATE_FORMAT),
			'firstOccurrence' => $record->getFirstOccurrence()?->format(self::DATE_FORMAT),
			'count'           => $record->getCount(),
			// the trace itself is left to get_record_detail, it is far too long for a list
			'hasStackTrace'   => $record instanceof RecordLogTrace && $record->getStackTrace() !== null,
		];
	}

	/**
	 * The name the record type goes by in the database, which is shorter and more stable than the
	 * class name a custom record type would bring.
	 */
	private function discriminator(Record $record): string {
		return $this->em->getClassMetadata($record::class)->discriminatorValue;
	}
}
