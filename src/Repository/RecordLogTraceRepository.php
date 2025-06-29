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
        string $newStatus,
        DateTimeInterface $lastDate,
        string $previousStatus
    ): QueryBuilder {
        $qb = $this->createQueryBuilder("l");
        if ($newStatus == 'resolved' && $this->clearStackTrace) {
            $qb = $qb->set('l.stackTrace', 'NULL');
        }

        return $qb;
    }


    public function setStatusBetween(
        array $projects,
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $newStatus,
        string $previousStatus = 'new',
		?callable $qbCreator = null
    ): void {
        $this->recordRepository->setStatusBetween(
            $projects,
            $from, $to,
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
		?callable $qbCreator = null
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
