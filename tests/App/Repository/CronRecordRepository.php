<?php

namespace BugCatcher\Tests\App\Repository;

use BugCatcher\Tests\App\Entity\RecordCron;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use BugCatcher\Repository\RecordRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @extends ServiceEntityRepository<RecordCron>
 */
class CronRecordRepository extends RecordRepository {
	public function __construct(ManagerRegistry $registry, EventDispatcherInterface $dispatcher) {
		parent::__construct($registry, $dispatcher, RecordCron::class);
	}

	//    /**
	//     * @return CronRecord[] Returns an array of CronRecord objects
	//     */
	//    public function findByExampleField($value): array
	//    {
	//        return $this->createQueryBuilder('c')
	//            ->andWhere('c.exampleField = :val')
	//            ->setParameter('val', $value)
	//            ->orderBy('c.id', 'ASC')
	//            ->setMaxResults(10)
	//            ->getQuery()
	//            ->getResult()
	//        ;
	//    }

	//    public function findOneBySomeField($value): ?CronRecord
	//    {
	//        return $this->createQueryBuilder('c')
	//            ->andWhere('c.exampleField = :val')
	//            ->setParameter('val', $value)
	//            ->getQuery()
	//            ->getOneOrNullResult()
	//        ;
	//    }
}
