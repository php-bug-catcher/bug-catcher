<?php

namespace BugCatcher\Repository;

use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordLog;
use BugCatcher\Entity\RecordLogWithholder;
use BugCatcher\Entity\RecordStatus;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @extends ServiceEntityRepository<RecordLogWithholder>
 *
 * @method RecordLogWithholder|null find($id, $lockMode = null, $lockVersion = null)
 * @method RecordLogWithholder|null findOneBy(array $criteria, array $orderBy = null)
 * @method RecordLogWithholder[]    findAll()
 * @method RecordLogWithholder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class RecordLogWithholderRepository extends ServiceEntityRepository implements RecordRepositoryInterface
{
	public function __construct(
		ManagerRegistry $registry,
        private readonly RecordRepositoryInterface $recordRepository,
	) {
        parent::__construct($registry, RecordLogWithholder::class);
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
            $qbCallback
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
            $qbCallback
        );
    }
}
