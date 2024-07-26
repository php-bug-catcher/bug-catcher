<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 16:08
 */
namespace PhpSentinel\BugCatcher\EventSubscriber;

use PhpSentinel\BugCatcher\DTO\NotifierStatus;
use PhpSentinel\BugCatcher\Entity\Project;
use PhpSentinel\BugCatcher\Enum\Importance;
use PhpSentinel\BugCatcher\Event\NotifyCalculateEvent;
use PhpSentinel\BugCatcher\Repository\RecordRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class NotifyCalculateListener {


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

		$status = new NotifierStatus();
		$event->addStatus($status);

		$records = $this->recordRepo->createQueryBuilder("record")
//				->from(Record::class, "record")
			->join("record.project", "project")
			->select("COUNT(record) as count")
			->where("record.status like :status")
			->andWhere("record.project IN (:projects)")
			->setParameter("status", 'new')
			->setParameter('projects', $projects)
			->groupBy("record.project")
			->getQuery()->enableResultCache(10)->getResult();
		foreach ($records as $record) {
			$status->incrementImportance(Importance::Normal, $record['count'], $event->notifier->getThreshold());
		}
	}

	private function calculateSameErrors(NotifyCalculateEvent $event, array $projects): void {
		$status = new NotifierStatus();
		$event->addStatus($status);

		$records = $this->recordRepo->createQueryBuilder("record")
//			->from(Record::class, "record")
			->select("COUNT(record) as count")
			->where("record.status like :status")
			->andWhere("record.project IN (:projects)")
			->setParameter("status", 'new')
			->setParameter('projects', $projects)
			->groupBy("record.hash")
			->getQuery()->enableResultCache(10)->getResult();

		foreach ($records as $record) {
			$status->incrementImportance(Importance::High, $record['count'], $event->notifier->getThreshold());
		}
	}


}