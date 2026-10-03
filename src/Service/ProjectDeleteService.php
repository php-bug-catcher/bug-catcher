<?php

declare(strict_types=1);

namespace BugCatcher\Service;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordLogWithholder;
use BugCatcher\Service\Perf\Retention\ChunkedDeleter;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Removes a project and everything that points at it.
 *
 * `$em->remove($project)` on its own is one `DELETE FROM project`, and what happens to the rows
 * referencing it is then up to the foreign keys - which do not all agree. `record.project_id` and
 * `perf_bucket.project_id` carry `ON DELETE CASCADE`, `record_log_withholder.project_id` does not,
 * so the statement is refused by the database and the project stays. Worse, where the cascade does
 * fire it is the database deleting rows the ORM never saw, so nothing tells the dashboard that the
 * records went away.
 *
 * Hence the dependents are deleted here, explicitly, and the project row last. Records go through
 * {@see BatchRecordDeleteInterface} rather than a statement of their own: that is the one place
 * that knows every table of the joined hierarchy, including the subtypes an application added, and
 * it refuses to run while one of them is unaccounted for. Perf buckets go through
 * {@see ChunkedDeleter} because a monitored project holds a row per route per minute, and one
 * unbounded DELETE over that is an incident rather than a click.
 *
 * Nothing wraps the whole thing in a transaction. Deleting the records of a busy project is
 * millions of rows; held open as one transaction it would block every writer for as long as it
 * runs. Done in chunks, each statement is short, and a run that is interrupted has still made
 * progress: the project row is the last to go, so what is left is the same project with fewer
 * dependents, and deleting it again finishes the job.
 */
final readonly class ProjectDeleteService {

	/**
	 * How many records are named in one `DELETE ... WHERE id IN (...)`. Large enough that a
	 * project with a million records is not a million round trips, small enough to stay well
	 * inside MySQL's placeholder limit.
	 */
	public const int DEFAULT_CHUNK_SIZE = 5_000;

	public function __construct(
		private EntityManagerInterface     $em,
		private BatchRecordDeleteInterface $batchDelete,
		private ChunkedDeleter             $deleter,
		private int                        $chunkSize = self::DEFAULT_CHUNK_SIZE,
	) {
		if ($chunkSize < 1) {
			throw new InvalidArgumentException(sprintf('A chunk holds at least one record, not %d.', $chunkSize));
		}
	}

	/** @param Project $project must be managed - it is this call that detaches it */
	public function delete(Project $project): void {
		$this->deleteRecords($project);
		$this->deletePerfBuckets($project);
		$this->deleteWithholders($project);

		// the memberships in user_project and notifier_project are deleted by the ORM with the
		// project itself: Doctrine clears the join tables of a many-to-many from either side
		$this->em->remove($project);
		$this->em->flush();
	}

	private function deleteRecords(Project $project): void {
		$metadata = $this->em->getClassMetadata(Record::class);
		$sql      = sprintf(
			'SELECT %s FROM %s WHERE %s = ? LIMIT %d',
			$metadata->getSingleIdentifierColumnName(),
			$metadata->getTableName(),
			$metadata->getSingleAssociationJoinColumnName('project'),
			$this->chunkSize,
		);
		$id       = $project->getId()->toBinary();

		$previous = null;
		while ($ids = $this->em->getConnection()->fetchFirstColumn($sql, [$id])) {
			// an implementation that reports success without deleting anything would otherwise
			// make this loop fetch the same chunk forever; sorted because the statement has no
			// ORDER BY and the same rows may well come back in a different order
			$chunk = $ids;
			sort($chunk);
			if ($chunk === $previous) {
				throw new RuntimeException(sprintf(
					'%s left the records of project "%s" in place, so they cannot be deleted. '
					. 'deleteByIds() is expected to delete the records it is given.',
					$this->batchDelete::class,
					$project->getCode(),
				));
			}
			$previous = $chunk;

			$this->batchDelete->deleteByIds($ids, [$project]);
		}
	}

	/** Extra metrics go with their bucket through the foreign key, as in the retention purge. */
	private function deletePerfBuckets(Project $project): void {
		$this->deleteByProject(PerfBucket::class, $project);
	}

	private function deleteWithholders(Project $project): void {
		$this->deleteByProject(RecordLogWithholder::class, $project);
	}

	/**
	 * Column and table names come from the mapping rather than from literals - the naming strategy
	 * belongs to the application.
	 *
	 * @param class-string $class
	 */
	private function deleteByProject(string $class, Project $project): void {
		$metadata = $this->em->getClassMetadata($class);

		$this->deleter->delete(
			$this->em->getConnection(),
			$metadata->getTableName(),
			sprintf('%s = ?', $metadata->getSingleAssociationJoinColumnName('project')),
			[$project->getId()->toBinary()],
		);
	}
}
