<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 31. 5. 2024
 * Time: 20:33
 */
namespace PhpSentinel\BugCatcher\Repository;

use PhpSentinel\BugCatcher\Entity\Record;
use PhpSentinel\BugCatcher\Entity\RecordStatus;

interface RecordRepositoryInterface {
	public function setStatusOlderThan(\DateTimeInterface $lastDate, $newStatus, $previousStatus = RecordStatus::NEW): void;

	public function setStatus(Record $log, \DateTimeInterface $lastDate, $newStatus, $previousStatus = RecordStatus::NEW): void ;
}