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
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;

interface RecordRepositoryInterface extends ServiceEntityRepositoryInterface,  ObjectRepository, Selectable
{

    /**
	 * @param DateTimeImmutable $to
     * @param Project[] $projects
     */
    public function setStatusBetween(
        array $projects,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
        string $newStatus,
        string $previousStatus = 'new',
		?callable $qbCreator = null
    ): void;

    public function setStatus(
        Record $log,
		DateTimeImmutable $lastDate,
        string $newStatus,
        string $previousStatus = 'new',
        bool $flush = false,
		?callable $qbCreator = null
    );

}