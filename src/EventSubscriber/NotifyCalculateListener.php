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

	/** @return array<string, Project> keyed by binary UUID */
	private function buildProjectMap(NotifyCalculateEvent $event): array {
		$map = [];
		foreach ($event->notifier->getProjects() as $p) {
			if ($p->isEnabled()) {
				$map[$p->getId()->toBinary()] = $p;
			}
		}
		return $map;
	}

	private function calculateProjectErrors(NotifyCalculateEvent $event, array $projects): void {
		$rows = $this->recordRepo->createQueryBuilder("record")
			->select("IDENTITY(record.project) as projectId, COUNT(record.id) as count")
			->where("record.status = :status")
			->andWhere("record.project IN (:projects)")
			->setParameter("status", 'new')
			->setParameter('projects', $projects)
			->groupBy("record.project")
			->getQuery()->enableResultCache(10)->getResult();

		$projectMap = $this->buildProjectMap($event);
		foreach ($rows as $row) {
			$project = $projectMap[$row['projectId']] ?? null;
			if (!$project) {
				continue;
			}
			$status = new NotifierStatus($project);
			$event->addStatus($status);
			$status->incrementImportance(Importance::Normal, $row['count'], $event->notifier->getThreshold());
		}
	}

	private function calculateSameErrors(NotifyCalculateEvent $event, array $projects): void {
		$rows = $this->recordRepo->createQueryBuilder("record")
			->select("IDENTITY(record.project) as projectId, COUNT(record.id) as count")
			->where("record.status = :status")
			->andWhere("record.project IN (:projects)")
			->setParameter("status", 'new')
			->setParameter('projects', $projects)
			->groupBy("record.project, record.hash")
			->getQuery()->enableResultCache(10)->getResult();

		$projectMap = $this->buildProjectMap($event);
		$statuses   = [];
		foreach ($rows as $row) {
			$project = $projectMap[$row['projectId']] ?? null;
			if (!$project) {
				continue;
			}
			$key = $row['projectId'];
			$status = $statuses[$key] ?? null;
			if (!$status) {
				$status = new NotifierStatus($project);
				$statuses[$key] = $status;
				$event->addStatus($status);
			}
			$status->incrementImportance(Importance::High, $row['count'], $event->notifier->getThreshold());
		}
	}


}
