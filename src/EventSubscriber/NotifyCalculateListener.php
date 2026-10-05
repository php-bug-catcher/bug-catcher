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
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Enum\Importance;
use BugCatcher\Event\NotifyCalculateEvent;
use BugCatcher\Repository\RecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class NotifyCalculateListener
{

	/**
	 * The three components `bug_catcher.notifier_components` offers, as the switch below spells
	 * them. A custom one is a listener of its own on {@see NotifyCalculateEvent}; this switch is
	 * closed - see docs/notifiers.md.
	 */
	private const string PROJECT_ERRORS = 'project-error-count';

	private const string SAME_ERRORS = 'same-error-count';

	private const string PERF_REGRESSIONS = 'perf-regression-count';

	public function __construct(
		private readonly RecordRepository $recordRepo,
		private readonly EntityManagerInterface $em,
	) {}

	public function __invoke(NotifyCalculateEvent $event): void {


		$projects = array_filter($event->notifier->getProjects()->toArray(), fn(Project $p) => $p->isEnabled());
		$projects = array_map(fn(Project $p) => $p->getId()->toBinary(), $projects);

		switch ($event->notifier->getComponent()) {
			case self::PROJECT_ERRORS:
				$this->calculateProjectErrors($event, $projects);
				break;
			case self::SAME_ERRORS:
				$this->calculateSameErrors($event, $projects);
				break;
			case self::PERF_REGRESSIONS:
				$this->calculatePerfRegressions($event, $projects);
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

	/**
	 * Every unresolved record of a project, whatever kind it is.
	 *
	 * Deliberately not filtered by type, which is also why a performance regression counts here:
	 * it is a `Record` with `status = new`, and somebody has to look at it.
	 *
	 * The queries in this class are **not** result-cached, and that is load-bearing rather than an
	 * omission. They used to carry `enableResultCache(10)`, and because
	 * {@see \BugCatcher\Service\Perf\Detection\RecordPerformanceWriter} dispatches one `RecordEvent`
	 * per written record in a tight loop, the second and every later event of one
	 * `app:perf:detect` run read the counts from before the flush - so a notifier could fail to
	 * cross its threshold on the very run that wrote the records. Caching the numbers a
	 * notification is decided from trades correctness for a saving on the ingest path, which is
	 * what {@see \BugCatcher\Service\RecordLogWithholder} is for.
	 */
	private function calculateProjectErrors(NotifyCalculateEvent $event, array $projects): void {
		$rows = $this->recordRepo->createQueryBuilder("record")
			->select("IDENTITY(record.project) as projectId, COUNT(record.id) as count")
			->where("record.status = :status")
			->andWhere("record.project IN (:projects)")
			->setParameter("status", 'new')
			->setParameter('projects', $projects)
			->groupBy("record.project")
			->getQuery()->getResult();

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
			->getQuery()->getResult();

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

	/**
	 * Only the routes whose performance regressed, so a notifier can be pointed at them alone.
	 *
	 * The other two components count every unresolved `Record`, which means a regression already
	 * raises a notifier configured for log errors - and at a threshold chosen for log errors.
	 * Twenty exceptions and twenty slow routes are not the same news, and an installation that
	 * wants to be told about the second at its own threshold had no way to say so.
	 *
	 * `INSTANCE OF` filters on the discriminator alone, so this reads no subtype table despite
	 * being rooted at the JOINED `Record` - the same trick {@see \BugCatcher\Mcp\RecordFinder} uses.
	 * `Importance::High`, like `same-error-count`: a regression is already deduplicated by route
	 * and metric, so each one counted here is a distinct route that got slower.
	 */
	private function calculatePerfRegressions(NotifyCalculateEvent $event, array $projects): void {
		$rows = $this->recordRepo->createQueryBuilder("record")
			->select("IDENTITY(record.project) as projectId, COUNT(record.id) as count")
			->where("record.status = :status")
			->andWhere("record.project IN (:projects)")
			->andWhere("record INSTANCE OF :type")
			->setParameter("status", 'new')
			->setParameter('projects', $projects)
			// the discriminator value, read from the mapping rather than written out as 'perf':
			// the map lives in the application's own Record.orm.xml - see docs/custom_record.md -
			// and this is the form INSTANCE OF binds, as in Twig\Components\LogList::init()
			->setParameter('type', $this->em->getClassMetadata(RecordPerformance::class)->discriminatorValue)
			->groupBy("record.project")
			->getQuery()->getResult();

		$projectMap = $this->buildProjectMap($event);
		foreach ($rows as $row) {
			$project = $projectMap[$row['projectId']] ?? null;
			if (!$project) {
				continue;
			}
			$status = new NotifierStatus($project);
			$event->addStatus($status);
			$status->incrementImportance(Importance::High, $row['count'], $event->notifier->getThreshold());
		}
	}


}
