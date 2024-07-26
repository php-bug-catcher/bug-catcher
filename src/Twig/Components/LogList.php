<?php

namespace PhpSentinel\BugCatcher\Twig\Components;

use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use PhpSentinel\BugCatcher\Controller\AbstractController;
use PhpSentinel\BugCatcher\Entity\Project;
use PhpSentinel\BugCatcher\Entity\Record;
use PhpSentinel\BugCatcher\Repository\RecordRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\MapDateTime;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class LogList extends AbstractController {
	use DefaultActionTrait;

	#[LiveProp]
	public string $status;

	#[LiveProp]
	public string $id;

	public function __construct(
		private readonly RecordRepository $recordRepo,
		private ManagerRegistry $registry,
		#[Autowire(param: 'dashboard_list_items')]
		private array           $classes
	) {
		$this->id = uniqid();
	}

	/**
	 * @return Record[]
	 */
	public function getLogs(): array {

		$em            = $this->registry->getManager();
		$classMetadata = $em->getClassMetadata(Record::class);
		$discriminatorMap = $classMetadata->discriminatorMap;
		$discriminatorMap = array_flip($discriminatorMap);
		$keys          = array_map(fn($class) => $discriminatorMap[$class]??null, $this->classes);

		/** @var Record[] $records */
		$records = $this->recordRepo->createQueryBuilder("record")
			->where("record.status like :status")
			->andWhere("record INSTANCE OF :class")
			->andWhere("record.project IN (:projects)")
			->setParameter("status", $this->status . '%')
			->setParameter("class", $keys)
			->setParameter('projects',
				array_map(fn(Project $p) => $p->getId()->toBinary(), $this->getUser()->getActiveProjects()->toArray())
			)
			->orderBy("record.date", "DESC")
			->setMaxResults(100)
			->getQuery()->getResult();
		$logs = [];
		foreach ($records as $row) {
			$record = $logs[$row->getHash()] = $logs[$row->getHash()]??$row->setCount(0);
			$record->setCount($record->getCount() + 1);
		}

		return array_values($logs);
	}


	#[LiveAction]
	public function clearAll(
		#[LiveArg] #[MapDateTime(format: "Y-m-d-H-i-s")] DateTimeImmutable $date,
	) {
		$rows = $this->recordRepo->createQueryBuilder("record")
			->addSelect('TYPE(record) as type')
			->where("record.status = :status")
			->andWhere("record.date <= :date")
			->setParameter("status", $this->status)
			->setParameter("date", $date)
			->groupBy('type')
			->getQuery()->getResult();
		foreach ($rows as $row) {
			$class = $row['0']::class;
			$repo  = $this->registry->getRepository($class);
			$repo->setStatusOlderThan(
				$this->getUser()->getProjects()->toArray(),
				$date,
				'resolved',
				$this->status
			);

		}

	}
}
