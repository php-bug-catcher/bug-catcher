<?php

namespace App\Repository;

use App\Entity\LogRecord;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LogRecord>
 *
 * @method LogRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method LogRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method LogRecord[]    findAll()
 * @method LogRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LogRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LogRecord::class);
    }

    public function save(LogRecord $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LogRecord $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function createEmpty(bool $flush): LogRecord
    {
        $entity = new LogRecord();

        $this->save($entity, $flush);

        return $entity;
    }

    public function getQBWith(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l');

        return $qb;
    }

    public function getQBBlank(): QueryBuilder
    {
        return $this->createQueryBuilder('l')->setMaxResults(0);
    }

	public function checkOlderThan(DateTimeImmutable $lastDate): void {
		$qb = $this->createQueryBuilder('l');
		$qb->update()
			->set('l.checked', true)
			->where('l.date <= :date')
			->setParameter('date', $lastDate)
			->getQuery()
			->execute();
	}

	public function check(LogRecord $log, DateTimeImmutable $lastDate): void {
		$this->createQueryBuilder('l')
			->update()
			->set('l.checked', true)
			->where('l.checked = 0')
			->andWhere('l.message = :message')
			->andWhere('l.date <= :date')
			->setParameter('date', $lastDate)
			->setParameter('message', $log->getMessage())
			->getQuery()
			->execute();
	}


}
