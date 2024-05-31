<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 31. 5. 2024
 * Time: 15:54
 */
namespace PhpSentinel\BugCatcher\Repository;

use Doctrine\ORM\QueryBuilder;
use PhpSentinel\BugCatcher\Entity\RecordLogTrace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PhpSentinel\BugCatcher\Entity\RecordStatus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @method RecordLogTrace|null find($id, $lockMode = null, $lockVersion = null)
 * @method RecordLogTrace|null findOneBy(array $criteria, array $orderBy = null)
 * @method RecordLogTrace[] findAll()
 * @method RecordLogTrace[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RecordLogTraceRepository extends RecordRepository implements RecordRepositoryInterface{
	public function __construct(
		ManagerRegistry $registry,
		#[Autowire(env: 'CLEAR_STACKTRACE_ON_FIXED')]
		protected bool    $clearStackTrace) {
		parent::__construct($registry,  RecordLogTrace::class);
	}

	protected function getUpdateStatusQB($newStatus, \DateTimeInterface $lastDate, mixed $previousStatus): QueryBuilder {
		$qb = parent::getUpdateStatusQB($newStatus, $lastDate, $previousStatus);

		if ($newStatus == RecordStatus::RESOLVED && $this->clearStackTrace) {
			$qb = $qb->set('l.stackTrace', 'NULL');
		}

		return $qb;
	}


}
