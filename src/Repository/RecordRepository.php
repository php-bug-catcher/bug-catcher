<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 31. 5. 2024
 * Time: 15:53
 */
namespace BugCatcher\Repository;

use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\Record;
use BugCatcher\Enum\RecordEventType;
use BugCatcher\Event\RecordEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @method Record|null find($id, $lockMode = null, $lockVersion = null)
 * @method Record|null findOneBy(array $criteria, array $orderBy = null)
 * @method Record[] findAll()
 * @method Record[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RecordRepository extends ServiceEntityRepository {
	public function __construct(
		ManagerRegistry                    $registry,
		protected EventDispatcherInterface $dispatcher,
										   $class = Record::class
	) {
		parent::__construct($registry, $class);
	}

	/**
	 * @param Project[] $projects
	 */
	public function setStatusOlderThan(array $projects, DateTimeInterface $lastDate, string $newStatus, string $previousStatus = 'new'): void {
		$qb = $this->getUpdateStatusQB($newStatus, $lastDate, $previousStatus);

		$qb
			->andWhere("l.project IN (:projects)")
			->setParameter('projects', array_map(fn(Project $s) => $s->getId()->toBinary(), $projects))
			->getQuery()
			->execute();
		$this->dispatcher->dispatch(new RecordEvent(null, RecordEventType::BATCH_UPDATED, $projects));
	}

	public function setStatus(
		Record $log, DateTimeInterface $lastDate, string $newStatus, string $previousStatus = 'new', bool $flush = false): void {
		$qb = $this->getUpdateStatusQB($newStatus, $lastDate, $previousStatus);
		$qb
			->andWhere('l.hash = :hash')
			->setParameter('hash', $log->getHash())
			->getQuery()
			->execute();
		$this->dispatcher->dispatch(new RecordEvent($log, RecordEventType::UPDATED, [$log->getProject()]));
		if ($flush) {
			$this->getEntityManager()->flush();
		}
	}

	protected function getUpdateStatusQB(string $newStatus, DateTimeInterface $lastDate, string $previousStatus): QueryBuilder {
		$qb = $this->createQueryBuilder('l');
		$qb = $qb->update()
			->set('l.status', "'{$newStatus}'")
			->andWhere('l.date <= :date')
			->andWhere('l.status = :status')
			->setParameter('date', $lastDate)
			->setParameter('status', $previousStatus);

		return $qb;
	}
}
