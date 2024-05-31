<?php

namespace PhpSentinel\BugCatcher\Repository;

use PhpSentinel\BugCatcher\Entity\RecordPing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecordPing>
 *
 * @method RecordPing|null find($id, $lockMode = null, $lockVersion = null)
 * @method RecordPing|null findOneBy(array $criteria, array $orderBy = null)
 * @method RecordPing[]    findAll()
 * @method RecordPing[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RecordPingRepository extends RecordRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecordPing::class);
    }

    public function save(RecordPing $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RecordPing $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function createEmpty(bool $flush): RecordPing
    {
        $entity = new RecordPing();

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
