<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 16:08
 */
namespace BugCatcher\EventSubscriber;

use BugCatcher\DTO\NotifierStatus;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\Importance;
use BugCatcher\Event\NotifyCalculateEvent;
use BugCatcher\Repository\RecordRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class NotifyCalculateListener
{


	public function __construct(
		private readonly RecordRepository $recordRepo,
	) {}

	public function __invoke(NotifyCalculateEvent $event): void {


		$projects = array_filter($event->notifier->getProjects()->toArray(), fn(Project $p) => $p->isEnabled());
		$projects = array_map(fn(Project $p) => $p->getId()->toBinary(), $projects);

		switch ($event->notifier->getComponent()) {
			case 'project-error-count':
				$this->calculateProjectErrors($event, $projects);
				break;
			case 'same-error-count':
				$this->calculateSameErrors($event, $projects);
				break;
		}
	}

	private function calculateProjectErrors(NotifyCalculateEvent $event, array $projects): void {
		$records = $this->recordRepo->createQueryBuilder("record")
			->select("project, COUNT(record.id) as count")
			->join("record.project", "project")
			->where("record.status = :status")
			->andWhere("record.project IN (:projects)")
			->setParameter("status", 'new')
			->setParameter('projects', $projects)
			->groupBy("project.id")
			->getQuery()->enableResultCache(10)->getResult();
		foreach ($records as $record) {
			$status = new NotifierStatus($record[0]);
			$event->addStatus($status);
			$status->incrementImportance(Importance::Normal, $record['count'], $event->notifier->getThreshold());
		}
	}

	private function calculateSameErrors(NotifyCalculateEvent $event, array $projects): void {
		$records = $this->recordRepo->createQueryBuilder("record")
			->select("project, COUNT(record.id) as count")
			->join("record.project", "project")
			->where("record.status = :status")
			->andWhere("record.project IN (:projects)")
			->setParameter("status", 'new')
			->setParameter('projects', $projects)
			->groupBy("project.id, record.hash")
			->getQuery()->enableResultCache(10)->getResult();
		$statuses = [];
		foreach ($records as $record) {
			$key    = $record[0]->getId()->__toString();
			$status = $statuses[$key] ?? null;
			if (!$status) {
				$status = new NotifierStatus($record[0]);
				$statuses[$key] = $status;
				$event->addStatus($status);
			}
			$status->incrementImportance(Importance::High, $record['count'], $event->notifier->getThreshold());
		}
	}


}