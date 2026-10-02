<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection;

use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Enum\RecordEventType;
use BugCatcher\Event\RecordEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Turns findings into records.
 *
 * The hash is set here because nothing else in this path does it, and without it the dashboard
 * would show one row per five-minute run instead of one row with a count.
 *
 * The event is what the design means by "the notifiers fire for free": over HTTP it is
 * `RecordLogSubscriber` that dispatches it after the write, and a command has no kernel view
 * event to hang that on. One event per record rather than one per batch, because that is the
 * contract {@see RecordEventType::CREATED} already has - the cost is that `RecordListener`
 * recomputes notifier statuses once per regressed route, which for a cron run over a handful of
 * routes is the cheaper mistake to make.
 */
final readonly class RecordPerformanceWriter
{
	public function __construct(
		private EntityManagerInterface $em,
		private EventDispatcherInterface $dispatcher,
	) {
	}

	/**
	 * @param iterable<AnomalyFinding> $findings
	 * @return list<RecordPerformance>
	 */
	public function write(iterable $findings): array
	{
		$records = [];

		foreach ($findings as $finding) {
			$record = new RecordPerformance(
				$finding->project,
				$finding->path,
				$finding->metric,
				$finding->unit,
				$finding->baseline,
				$finding->observed,
				$finding->windowAt,
			);
			$record->setHash($record->calculateHash());

			$this->em->persist($record);
			$records[] = $record;
		}

		if ($records === []) {
			return [];
		}

		$this->em->flush();

		foreach ($records as $record) {
			$this->dispatcher->dispatch(new RecordEvent($record, RecordEventType::CREATED, [$record->getProject()]));
		}

		return $records;
	}
}
