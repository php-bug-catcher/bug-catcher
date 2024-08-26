<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 8. 2024
 * Time: 12:07
 */

namespace BugCatcher\Repository;

use BugCatcher\Entity\Project;
use BugCatcher\Entity\Record;
use DateTimeInterface;

interface RecordRepositoryInterface
{

    /**
     * @param Project[] $projects
     */
    public function setStatusOlderThan(
        array $projects,
        DateTimeInterface $lastDate,
        string $newStatus,
        string $previousStatus = 'new',
        callable $qbCreator = null
    ): void;

    public function setStatus(
        Record $log,
        DateTimeInterface $lastDate,
        string $newStatus,
        string $previousStatus = 'new',
        bool $flush = false,
        callable $qbCreator = null
    );

}