<?php

namespace BugCatcher\Repository;

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
class RecordLogRepository extends RecordRepository {
	public function __construct(
		ManagerRegistry $registry,
		EventDispatcherInterface $dispatcher
	) {
		parent::__construct($registry, $dispatcher, RecordLog::class);
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


}
