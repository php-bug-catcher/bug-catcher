<?php

namespace BugCatcher\Mcp;

use BugCatcher\Entity\Project;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * What the caller of `search_records` asked for.
 *
 * Every filter is optional; a criteria built with no arguments at all means "the unresolved errors
 * of every project and of every searchable record type, newest first".
 */
final class RecordSearchCriteria
{
	public const DEFAULT_STATUS = 'new';
	public const DEFAULT_LIMIT  = 25;
	public const MAX_LIMIT      = 100;

	/**
	 * @param Project|null           $project  null searches every project
	 * @param string|null            $status   matched as a prefix, null matches any status
	 * @param string|null            $code     the error code carried by the record
	 * @param int|null               $minLevel monolog level floor, inclusive
	 * @param DateTimeImmutable|null $from     inclusive
	 * @param DateTimeImmutable|null $to       inclusive
	 * @param int                    $limit    number of distinct errors, not of rows
	 * @param string|null            $type     a single discriminator value, null every searchable type
	 */
	public function __construct(
		public readonly ?Project           $project = null,
		public readonly ?string            $status = self::DEFAULT_STATUS,
		public readonly ?string            $code = null,
		public readonly ?int               $minLevel = null,
		public readonly ?DateTimeImmutable $from = null,
		public readonly ?DateTimeImmutable $to = null,
		public readonly int                $limit = self::DEFAULT_LIMIT,
		public readonly ?string            $type = null,
	) {
		if ($limit < 1 || $limit > self::MAX_LIMIT) {
			throw new InvalidArgumentException(sprintf(
				'The limit has to be between 1 and %d, got %d.', self::MAX_LIMIT, $limit,
			));
		}
		if ($from !== null && $to !== null && $from > $to) {
			throw new InvalidArgumentException('The start of the range is after its end.');
		}
	}
}
