<?php

namespace PhpSentinel\BugCatcher\Twig\Components;

use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use PhpSentinel\BugCatcher\Entity\Record;
use PhpSentinel\BugCatcher\Repository\RecordRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

		$rows = $this->recordRepo->createQueryBuilder("record")
			->addSelect('COUNT(record.id) as count')
			->where("record.status = :status")
			->andWhere("record INSTANCE OF :class")
			->setParameter("status", $this->status)
			->setParameter("class", $keys)
			->orderBy("record.date", "DESC")
			->groupBy("record.hash")
			->setMaxResults(100)
			->getQuery()->getResult();
		$logs = [];
		foreach ($rows as $row) {
			/** @var Record $log */
			$logs[] = $log = $row[0];
			$log->setCount($row['count']);
		}

		return $logs;
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
			$repo->setStatusOlderThan($date, 'resolved', $this->status);

		}

	}

	#[LiveAction]
	public function clearOne(
		#[LiveArg] Record                                                  $log,
		#[LiveArg] #[MapDateTime(format: "Y-m-d-H-i-s")] DateTimeImmutable $date,
		#[LiveArg] string $status
	) {
		$class = $log::class;
		$repo  = $this->registry->getRepository($class);
		$repo->setStatus($log, $date, $status, $this->status);

	}
}
