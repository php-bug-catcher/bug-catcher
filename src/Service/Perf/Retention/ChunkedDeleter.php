<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Retention;

use Doctrine\DBAL\Connection;
use InvalidArgumentException;

/**
 * `DELETE ... LIMIT n` in a loop, until the statement stops finding rows.
 *
 * On a table that holds a row per route per minute, one unbounded DELETE is not a cron job but an
 * incident: it holds locks and grows the undo log for as long as it runs, and everything writing
 * buckets meanwhile waits on it. In chunks each statement is short, and a run that is killed
 * halfway has still done real work rather than rolling all of it back.
 *
 * `LIMIT` on DELETE is MySQL's, like the rest of this module.
 */
final readonly class ChunkedDeleter
{
	public const int DEFAULT_CHUNK_SIZE = 10_000;

	public function __construct(private int $chunkSize = self::DEFAULT_CHUNK_SIZE)
	{
		if ($chunkSize < 1) {
			throw new InvalidArgumentException(sprintf('A chunk holds at least one row, not %d.', $chunkSize));
		}
	}

	/**
	 * @param string $where the condition, with `?` placeholders
	 * @param list<mixed> $params
	 * @param array<int, mixed> $types
	 * @return int how many rows went
	 */
	public function delete(
		Connection $connection,
		string $table,
		string $where,
		array $params = [],
		array $types = [],
	): int {
		$sql   = sprintf('DELETE FROM %s WHERE %s LIMIT %d', $table, $where, $this->chunkSize);
		$total = 0;

		do {
			$deleted = (int)$connection->executeStatement($sql, $params, $types);
			$total   += $deleted;
		} while ($deleted === $this->chunkSize);

		return $total;
	}
}
