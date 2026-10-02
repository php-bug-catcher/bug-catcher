<?php

declare(strict_types=1);

namespace BugCatcher\Tests\App\Service;

use BugCatcher\Enum\RecordEventType;
use BugCatcher\Event\RecordEvent;
use BugCatcher\Service\BatchRecordDeleteInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * What docs/custom_record.md tells an application to write once it adds a record type of its own.
 *
 * The test application has `RecordCron`, so the bundle's own `BatchRecordDeleteService` would
 * refuse to run - by design, because a subtype nobody names here leaves its rows behind. Having
 * the worked example in the test suite means the documented procedure is executed on every run
 * rather than only read.
 */
final readonly class CronBatchRecordDeleteService implements BatchRecordDeleteInterface
{
	public function __construct(
		private EntityManagerInterface $em,
		private EventDispatcherInterface $dispatcher,
	) {
	}

	public function deleteByIds(array $binaryIds, array $projects): void
	{
		if (empty($binaryIds)) {
			return;
		}

		$placeholders = implode(',', array_fill(0, count($binaryIds), '?'));
		$this->em->getConnection()->executeStatement(
			'DELETE record_log_trace, record_log, record_ping, record_performance, record_cron, record
             FROM record
             LEFT JOIN record_log ON record.id = record_log.id
             LEFT JOIN record_log_trace ON record_log.id = record_log_trace.id
             LEFT JOIN record_ping ON record.id = record_ping.id
             LEFT JOIN record_performance ON record.id = record_performance.id
             LEFT JOIN record_cron ON record.id = record_cron.id
             WHERE record.id IN (' . $placeholders . ')',
			$binaryIds,
		);

		$this->dispatcher->dispatch(new RecordEvent(null, RecordEventType::BATCH_DELETED, $projects));
	}
}
