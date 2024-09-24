<?php

namespace BugCatcher\Repository;

use BugCatcher\Entity\Project;
use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordPing;
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @extends ServiceEntityRepository<RecordPing>
 *
 * @method RecordPing|null find($id, $lockMode = null, $lockVersion = null)
 * @method RecordPing|null findOneBy(array $criteria, array $orderBy = null)
 * @method RecordPing[]    findAll()
 * @method RecordPing[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class RecordPingRepository extends ServiceEntityRepository implements RecordRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        EventDispatcherInterface $dispatcher,
        private readonly RecordRepositoryInterface $recordRepository,
    ) {
        parent::__construct($registry, RecordPing::class);
    }

    public function save(RecordPing $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }




    public function getLastRecord(Project $project, string $maxLife = '-1 hour'): ?RecordPing
    {
        return $this->getQBWith(project: $project)
            ->andWhere("r.date >= :date")
            ->orderBy('r.date', 'DESC')
            ->setParameter('date', new DateTime($maxLife))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }


    public function setStatusBetween(
        array $projects,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $newStatus,
        string $previousStatus = 'new',
        callable $qbCreator = null
    ): void {
        $this->recordRepository->setStatusBetween($projects, $from, $to, $newStatus, $previousStatus, $qbCreator);
    }

    public function setStatus(
        Record $log,
        DateTimeInterface $lastDate,
        string $newStatus,
        string $previousStatus = 'new',
        bool $flush = false,
        callable $qbCreator = null
    ) {
        $this->recordRepository->setStatus($log, $lastDate, $newStatus, $previousStatus, $flush, $qbCreator);
    }
}
