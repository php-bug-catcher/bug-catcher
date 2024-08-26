<?php

namespace BugCatcher\Repository;

use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordLog;
use BugCatcher\Entity\RecordStatus;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @extends ServiceEntityRepository<Record>
 *
 * @method Record|null find($id, $lockMode = null, $lockVersion = null)
 * @method Record|null findOneBy(array $criteria, array $orderBy = null)
 * @method Record[]    findAll()
 * @method Record[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class RecordLogRepository extends ServiceEntityRepository implements RecordRepositoryInterface
{
	public function __construct(
		ManagerRegistry $registry,
        private readonly RecordRepositoryInterface $recordRepository,
	) {
        parent::__construct($registry, RecordLog::class);
	}

	public function save(Record $entity, bool $flush = false): void {
		$this->getEntityManager()->persist($entity);

		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	public function remove(Record $entity, bool $flush = false): void {
		$this->getEntityManager()->remove($entity);

		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	public function createEmpty(bool $flush): Record {
		$entity = new RecordLog();

		$this->save($entity, $flush);

		return $entity;
	}

	public function getQBWith(): QueryBuilder {
		$qb = $this->createQueryBuilder('l');

		return $qb;
	}

	public function getQBBlank(): QueryBuilder {
		return $this->createQueryBuilder('l')->setMaxResults(0);
	}


    public function setStatusOlderThan(
        array $projects,
        DateTimeInterface $lastDate,
        string $newStatus,
        string $previousStatus = 'new',
        callable $qbCallback = null
    ): void {
        $this->recordRepository->setStatusOlderThan($projects, $lastDate, $newStatus, $previousStatus, $qbCallback);
    }

    public function setStatus(
        Record $log,
        DateTimeInterface $lastDate,
        string $newStatus,
        string $previousStatus = 'new',
        bool $flush = false,
        callable $qbCallback = null
    ) {
        $this->recordRepository->setStatus($log, $lastDate, $newStatus, $previousStatus, $flush, $qbCallback);
    }

}
