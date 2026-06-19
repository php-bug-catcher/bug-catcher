<?php

namespace BugCatcher\Service;

use BugCatcher\Entity\Project;
use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordLog;
use BugCatcher\Entity\RecordLogTrace;
use BugCatcher\Entity\RecordPing;
use BugCatcher\Enum\RecordEventType;
use BugCatcher\Event\RecordEvent;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class BatchRecordDeleteService implements BatchRecordDeleteInterface {

	private array $handledClasses = [
		RecordLog::class,
		RecordLogTrace::class,
		RecordPing::class,
	];

	public function __construct(
		private readonly EntityManagerInterface   $em,
		private readonly EventDispatcherInterface $dispatcher,
	) {
	}

	public function deleteByIds(array $binaryIds, array $projects): void {
		if (empty($binaryIds)) {
			return;
		}

		$this->assertNoUnknownSubtypes();

		$placeholders = implode(',', array_fill(0, count($binaryIds), '?'));
		$this->em->getConnection()->executeStatement(
			'DELETE record_log_trace, record_log, record_ping, record
             FROM record
             LEFT JOIN record_log ON record.id = record_log.id
             LEFT JOIN record_log_trace ON record_log.id = record_log_trace.id
             LEFT JOIN record_ping ON record.id = record_ping.id
             WHERE record.id IN (' . $placeholders . ')',
			$binaryIds
		);

		$this->dispatcher->dispatch(new RecordEvent(null, RecordEventType::BATCH_DELETED, $projects));
	}

	private function assertNoUnknownSubtypes(): void {
		$allClasses = array_values($this->em->getClassMetadata(Record::class)->discriminatorMap);
		$unknown    = array_diff($allClasses, $this->handledClasses);

		if (!empty($unknown)) {
			throw new LogicException(sprintf(
				'Unknown Record subtypes detected: [%s]. '
				. 'Extend %s, handle these types in your deleteByIds() method, '
				. 'and register your implementation as the "%s" service.',
				implode(', ', $unknown),
				self::class,
				BatchRecordDeleteInterface::class
			));
		}
	}
}
