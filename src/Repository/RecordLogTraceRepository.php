<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 31. 5. 2024
 * Time: 15:54
 */
namespace PhpSentinel\BugCatcher\Repository;

use PhpSentinel\BugCatcher\Entity\RecordLogTrace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method RecordLogTrace|null find($id, $lockMode = null, $lockVersion = null)
 * @method RecordLogTrace|null findOneBy(array $criteria, array $orderBy = null)
 * @method RecordLogTrace[] findAll()
 * @method RecordLogTrace[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RecordLogTraceRepository extends ServiceEntityRepository {
	public function __construct(ManagerRegistry $registry) {
		parent::__construct($registry, RecordLogTrace::class);
	}
}
