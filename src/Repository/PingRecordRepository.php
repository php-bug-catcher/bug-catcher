<?php

namespace App\Repository;

use App\Entity\PingRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PingRecord>
 *
 * @method PingRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method PingRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method PingRecord[]    findAll()
 * @method PingRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PingRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PingRecord::class);
    }

    public function save(PingRecord $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PingRecord $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function createEmpty(bool $flush): PingRecord
    {
        $entity = new PingRecord();

        $this->save($entity, $flush);

        return $entity;
    }

    public function getQBWith(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p');

        return $qb;
    }

    public function getQBBlank(): QueryBuilder
    {
        return $this->createQueryBuilder('p')->setMaxResults(0);
    }
}
