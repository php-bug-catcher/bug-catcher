<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 31. 5. 2024
 * Time: 15:53
 */

namespace BugCatcher\Repository;

use BugCatcher\Entity\Project;
use BugCatcher\Entity\Record;
use BugCatcher\Enum\RecordEventType;
use BugCatcher\Event\RecordEvent;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @method Record|null find($id, $lockMode = null, $lockVersion = null)
 * @method Record|null findOneBy(array $criteria, array $orderBy = null)
 * @method Record[] findAll()
 * @method Record[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class RecordRepository extends ServiceEntityRepository implements RecordRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        protected EventDispatcherInterface $dispatcher,
    ) {
        parent::__construct($registry, Record::class);
    }

    /**
     * @param DateTimeInterface $to
     * @param Project[] $projects
     */
    public function setStatusBetween(
        array $projects,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $newStatus,
        string $previousStatus = 'new',
        callable $qbCreator = null
    ): void {
        $qb = $this->getUpdateStatusQB($newStatus, $from, $to, $previousStatus, $qbCreator);

        $qb
            ->andWhere("l.project IN (:projects)")
            ->setParameter('projects', array_map(fn(Project $s) => $s->getId()->toBinary(), $projects))
            ->getQuery()
            ->execute();
        $this->dispatcher->dispatch(new RecordEvent(null, RecordEventType::BATCH_UPDATED, $projects));
    }

    public function setStatus(
        Record $log,
        DateTimeInterface $lastDate,
        string $newStatus,
        string $previousStatus = 'new',
        bool $flush = false,
        callable $qbCreator = null
    ): void {
        $qb = $this->getUpdateStatusQB($newStatus, $lastDate, new DateTimeImmutable(), $previousStatus, $qbCreator);
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

    protected function getUpdateStatusQB(
        string $newStatus,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $previousStatus,
        callable $qbCreator = null
    ): QueryBuilder {

        if ($qbCreator != null) {
            /** @var QueryBuilder $qb */
            $qb = call_user_func_array($qbCreator, [$newStatus, $from, $previousStatus]);
        } else {
            $qb = $this->createQueryBuilder('l');
        }
        $qb = $qb->update()
            ->set('l.status', "'{$newStatus}'")
            ->andWhere('l.date BETWEEN :from AND :to')
            ->andWhere('l.status = :status')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('status', $previousStatus);

        return $qb;
    }
}
