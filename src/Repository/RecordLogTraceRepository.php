<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 31. 5. 2024
 * Time: 15:54
 */

namespace BugCatcher\Repository;

use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordLogTrace;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method RecordLogTrace|null find($id, $lockMode = null, $lockVersion = null)
 * @method RecordLogTrace|null findOneBy(array $criteria, array $orderBy = null)
 * @method RecordLogTrace[] findAll()
 * @method RecordLogTrace[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class RecordLogTraceRepository extends ServiceEntityRepository implements RecordRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly RecordRepositoryInterface $recordRepository,
        protected readonly bool $clearStackTrace
    ) {
        parent::__construct($registry, RecordLogTrace::class);
    }

    protected function updateQb(
        QueryBuilder $qb,
        string $newStatus,
        DateTimeInterface $lastDate,
        string $previousStatus
    ): QueryBuilder {

        if ($newStatus == 'resolved' && $this->clearStackTrace) {
            $qb = $qb->set('l.stackTrace', 'NULL');
        }

        return $qb;
    }


    public function setStatusOlderThan(
        array $projects,
        DateTimeInterface $lastDate,
        string $newStatus,
        string $previousStatus = 'new',
        callable $qbCallback = null
    ): void {
        $this->recordRepository->setStatusOlderThan(
            $projects,
            $lastDate,
            $newStatus,
            $previousStatus,
            $this->updateQb(...)
        );
    }

    public function setStatus(
        Record $log,
        DateTimeInterface $lastDate,
        string $newStatus,
        string $previousStatus = 'new',
        bool $flush = false,
        callable $qbCallback = null
    ) {
        $this->recordRepository->setStatus(
            $log,
            $lastDate,
            $newStatus,
            $previousStatus,
            $flush,
            $this->updateQb(...)
        );
    }
}
