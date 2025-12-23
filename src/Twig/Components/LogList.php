<?php

namespace BugCatcher\Twig\Components;

use BugCatcher\Controller\AbstractController;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\Record;
use BugCatcher\Repository\RecordRepository;
use BugCatcher\Repository\RecordRepositoryInterface;
use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\MapDateTime;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
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
		$keys             = array_map(fn($class) => $discriminatorMap[$class] ?? null, $this->classes);

		if ($this->project) {
			$projects = [$this->project];
		} else {
			$projects = $this->getUser()->getActiveProjects()->toArray();
		}
		/** @var Record[] $records */
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

		$records = $qb
			->getQuery()->getResult();

		if ($records === []) {
			$this->checkMessage();
			return;
		}
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
	public function clearAll(
		#[LiveArg] #[MapDateTime(format: "Y-m-d-H-i-s")] DateTimeImmutable $from,
		#[LiveArg] #[MapDateTime(format: "Y-m-d-H-i-s")] DateTimeImmutable $to,
	) {
		$ids = $this->selectionManager->getSelection("main_logs")->getSelectedIdentifiers();

		$ids = array_map(function (string $hexId) {
			return (new Uuid($hexId))->toBinary();
		}, $ids);

		$rows = $this->recordRepo->createQueryBuilder("record")
			->where("record.id IN (:ids)")
			->setParameter("ids", $ids)
			->getQuery()->getResult();


		foreach ($rows as $record) {
			/** @var RecordRepositoryInterface $repo */
			$repo = $this->registry->getRepository($record::class);
			$repo->setStatus(
				$record,
				$from,
				'resolved',
				$this->status
			);

		}
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
