<?php

namespace PhpSentinel\BugCatcher\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PhpSentinel\BugCatcher\Entity\Notifier;

/**
 * @extends ServiceEntityRepository<Notifier>
 */
class NotifierRepository extends ServiceEntityRepository {
	public function __construct(ManagerRegistry $registry) {
		parent::__construct($registry, Notifier::class);
	}

	//    /**
	//     * @return Notifier[] Returns an array of Notifier objects
	//     */
	//    public function findByExampleField($value): array
	//    {
	//        return $this->createQueryBuilder('n')
	//            ->andWhere('n.exampleField = :val')
	//            ->setParameter('val', $value)
	//            ->orderBy('n.id', 'ASC')
	//            ->setMaxResults(10)
	//            ->getQuery()
	//            ->getResult()
	//        ;
	//    }

	//    public function findOneBySomeField($value): ?Notifier
	//    {
	//        return $this->createQueryBuilder('n')
	//            ->andWhere('n.exampleField = :val')
	//            ->setParameter('val', $value)
	//            ->getQuery()
	//            ->getOneOrNullResult()
	//        ;
	//    }
}
