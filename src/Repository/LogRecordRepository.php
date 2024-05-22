<?php

namespace App\Repository;

use App\Entity\LogRecord;
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
}
