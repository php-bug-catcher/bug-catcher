<?php

namespace PhpSentinel\BugCatcher\Repository;

use PhpSentinel\BugCatcher\Entity\Record;
use PhpSentinel\BugCatcher\Entity\RecordLog;
use PhpSentinel\BugCatcher\Entity\RecordStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @extends ServiceEntityRepository<Record>
 *
 * @method Record|null find($id, $lockMode = null, $lockVersion = null)
 * @method Record|null findOneBy(array $criteria, array $orderBy = null)
 * @method Record[]    findAll()
 * @method Record[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RecordLogRepository extends ServiceEntityRepository {
	public function __construct(
		ManagerRegistry $registry,
		string $className = RecordLog::class,
	) {
		parent::__construct($registry, $className);
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

	public function setStatusOlderThan(DateTimeImmutable $lastDate, $newStatus, $previousStatus = RecordStatus::NEW): void {
		$qb = $this->getUpdateStatusQB($newStatus, $lastDate, $previousStatus);

		$qb
			->getQuery()
			->execute();
	}

	public function setStatus(Record $log, DateTimeImmutable $lastDate, $newStatus, $previousStatus = RecordStatus::NEW): void {
		$qb = $this->getUpdateStatusQB($newStatus, $lastDate, $previousStatus);
		$qb
			->andWhere('l.message = :message')
			->setParameter('message', $log->getMessage())
			->getQuery()
			->execute();
	}

	protected function getUpdateStatusQB($newStatus, DateTimeImmutable $lastDate, mixed $previousStatus): QueryBuilder {
		$qb = $this->createQueryBuilder('l');
		$qb = $qb->update()
			->set('l.status', "'{$newStatus->value}'")
			->andWhere('l.date <= :date')
			->andWhere('l.status = :status')
			->setParameter('date', $lastDate)
			->setParameter('status', $previousStatus);

		if ($newStatus == RecordStatus::RESOLVED && $this->clearStackTrace) {
			$qb = $qb->set('l.stackTrace', 'NULL');
		}

		return $qb;
	}


}
