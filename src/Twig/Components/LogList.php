<?php

namespace BugCatcher\Twig\Components;

use BugCatcher\Controller\AbstractController;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\Record;
use BugCatcher\Repository\RecordRepository;
use BugCatcher\Service\BatchRecordDeleteInterface;
use DateTimeImmutable;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Tito10047\PersistentStateBundle\Selection\Service\SelectionManagerInterface;

#[AsLiveComponent]
final class LogList extends AbstractController {

	use DefaultActionTrait;

	#[LiveProp]
	public string $status;

	#[LiveProp]
	public ?Project $project = null;

	#[LiveProp]
	public string $id;
	/** @var Record[] */
	#[ExposeInTemplate]
	public array              $logs         = [];
	#[ExposeInTemplate]
	public ?DateTimeImmutable $from = null;
	#[ExposeInTemplate]
	public ?DateTimeImmutable $to   = null;
	#[LiveProp(writable: true)]
	public string             $query        = '';
	#[LiveProp()]
	public ?string            $funnyMessage = null;

	public function __construct(
		private readonly RecordRepository $recordRepo,
		#[Autowire(service: 'persistent_state.selection.manager.default')]
		private readonly SelectionManagerInterface $selectionManager,
		private readonly BatchRecordDeleteInterface $batchDelete,
		private ManagerRegistry           $registry,
		private array                     $classes,
		private array                     $noBugFunnyMessages
	) {
		$this->id = uniqid();
		$this->checkMessage();
	}

	public function init(): void {

		$em               = $this->registry->getManager();
		$classMetadata    = $em->getClassMetadata(Record::class);
		$discriminatorMap = $classMetadata->discriminatorMap;
		$discriminatorMap = array_flip($discriminatorMap);
		$keys             = array_values(array_filter(
			array_map(fn($class) => $discriminatorMap[$class] ?? null, $this->classes)
		));

		if ($this->project) {
			$projects = [$this->project];
		} else {
			$projects = $this->getUser()->getActiveProjects()->toArray();
		}
		$qb = $this->recordRepo->createQueryBuilder("record")
			->where("record.status like :status")
			->andWhere("record INSTANCE OF :class")
			->andWhere("record.project IN (:projects)")
			->setParameter("status", $this->status . '%')
			->setParameter("class", $keys)
			->setParameter('projects',
				array_map(fn(Project $p) => $p->getId()->toBinary(), $projects)
			)
			->orderBy("record.date", "DESC")
			->setMaxResults(100);

		if ($this->query) {
			$qb->andWhere("record.code = :query")
				->setParameter("query", $this->query);
		}

		$this->selectionManager->registerSelection("main_logs", $qb);

		// Deferred join: `Record` is a JOINED inheritance root, so any DQL query on it LEFT JOINs
		// every subtype table. Resolve the 100 ids on the base table alone (HINT_FORCE_PARTIAL_LOAD
		// strips those joins), then hydrate only those rows.
		// ids come back as the raw binary column values, like the :projects parameter above
		$ids = (clone $qb)
			->select("record.id")
			->getQuery()
			->setHint(Query::HINT_FORCE_PARTIAL_LOAD, true)
			->getSingleColumnResult();

		if ($ids === []) {
			$this->checkMessage();
			return;
		}

		/** @var Record[] $records */
		$records = $this->recordRepo->createQueryBuilder("record")
			->where("record.id IN (:ids)")
			->setParameter("ids", $ids)
			->orderBy("record.date", "DESC")
			->getQuery()->getResult();

		$this->from = $records[0]->getDate();
		$this->to   = $records[count($records) - 1]->getDate();

		$logs = [];
		foreach ($records as $row) {
			$record = $logs[$row->getHash()] = $logs[$row->getHash()] ?? $row->setCount(0);
			$record->setCount($record->getCount() + 1);
			$record->setFirstOccurrence($row->getDate());
		}

		$this->logs = array_values($logs);
		$this->checkMessage();
	}


	#[LiveAction]
	public function clearAll(): void {
		$ids = $this->selectionManager->getSelection("main_logs")->getSelectedIdentifiers();
		if (empty($ids)) {
			return;
		}

		$binaryIds = array_map(fn(string $hexId) => (new Uuid($hexId))->toBinary(), $ids);

		$projects = $this->project
			? [$this->project]
			: $this->getUser()->getActiveProjects()->toArray();

		$this->batchDelete->deleteByIds($binaryIds, $projects);
		$this->id = uniqid();
	}

	private function checkMessage(): void {
		if (!count($this->logs)) {
			if ($this->funnyMessage == null) {
				$this->funnyMessage = $this->noBugFunnyMessages[array_rand($this->noBugFunnyMessages)];
			}
		} else {
			$this->funnyMessage = null;
		}
	}
}
