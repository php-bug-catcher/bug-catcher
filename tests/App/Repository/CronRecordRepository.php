<?php

namespace BugCatcher\Tests\App\Repository;

use BugCatcher\Entity\Record;
use BugCatcher\Repository\RecordRepositoryInterface;
use BugCatcher\Tests\App\Entity\RecordCron;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use BugCatcher\Repository\RecordRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @extends ServiceEntityRepository<RecordCron>
 */
class CronRecordRepository extends ServiceEntityRepository implements RecordRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly RecordRepositoryInterface $recordRepository,
    ) {
        parent::__construct($registry, RecordCron::class);
	}

    public function setStatusOlderThan(
        array $projects,
        DateTimeInterface $lastDate,
        string $newStatus,
        string $previousStatus = 'new',
        callable $qbCreator = null
    ): void {
        $this->recordRepository->setStatusOlderThan($projects, $lastDate, $newStatus, $previousStatus, $qbCreator);
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
