<?php

declare(strict_types=1);

namespace BugCatcher\Repository;

use BugCatcher\Entity\Project;
use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordPerformance;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * The repository the mapping names for {@see RecordPerformance}.
 *
 * Status changes belong to the whole hierarchy, so they are delegated to
 * {@see RecordRepositoryInterface} rather than reimplemented - the same shape as
 * {@see RecordLogRepository}, and the reason it is delegation and not inheritance is that
 * {@see RecordRepository} is final.
 *
 * @extends ServiceEntityRepository<RecordPerformance>
 *
 * @method RecordPerformance|null find($id, $lockMode = null, $lockVersion = null)
 * @method RecordPerformance|null findOneBy(array $criteria, array $orderBy = null)
 * @method RecordPerformance[]    findAll()
 * @method RecordPerformance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class RecordPerformanceRepository extends ServiceEntityRepository implements RecordRepositoryInterface
{
	public function __construct(
		ManagerRegistry $registry,
		private readonly RecordRepositoryInterface $recordRepository,
	) {
		parent::__construct($registry, RecordPerformance::class);
	}

	/**
	 * Unresolved regressions of one project seen since an instant.
	 *
	 * Rows and not occurrences: records are deduplicated by hash, so one route regressing for an
	 * hour is one row with a count of twelve, and "12" on a dashboard row would read as twelve
	 * routes. What the cell is for is "how many things is the detector shouting about".
	 *
	 * DQL rather than `count()` with criteria because `date` and `status` live on the root of the
	 * hierarchy; the `INSTANCE OF` is implicit in rooting the query at the subtype.
	 */
	public function countOpenSince(Project $project, DateTimeImmutable $since): int
	{
		return (int)$this->createQueryBuilder('p')
			->select('COUNT(p.id)')
			->andWhere('p.project = :project')
			->andWhere('p.date >= :since')
			->andWhere('p.status = :status')
			->setParameter('project', $project->getId(), UuidType::NAME)
			->setParameter('since', $since)
			->setParameter('status', 'new')
			->getQuery()
			->getSingleScalarResult();
	}

	public function setStatusBetween(
		array $projects,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
		string $newStatus,
		string $previousStatus = 'new',
		?callable $qbCreator = null,
	): void {
		$this->recordRepository->setStatusBetween($projects, $from, $to, $newStatus, $previousStatus, $qbCreator);
	}

	public function setStatus(
		Record $log,
		DateTimeImmutable $lastDate,
		string $newStatus,
		string $previousStatus = 'new',
		bool $flush = false,
		?callable $qbCreator = null,
	): void {
		$this->recordRepository->setStatus($log, $lastDate, $newStatus, $previousStatus, $flush, $qbCreator);
	}
}
