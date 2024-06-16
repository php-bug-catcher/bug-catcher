<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 31. 5. 2024
 * Time: 20:33
 */
namespace PhpSentinel\BugCatcher\Repository;

use DateTimeInterface;
use PhpSentinel\BugCatcher\Entity\Record;

interface RecordRepositoryInterface {
	public function setStatusOlderThan(DateTimeInterface $lastDate, string $newStatus, string $previousStatus = 'new'): void;

	public function setStatus(Record $log, DateTimeInterface $lastDate, string $newStatus, string $previousStatus = 'new'): void;
}