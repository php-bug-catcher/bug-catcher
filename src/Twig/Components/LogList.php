<?php

namespace PhpSentinel\BugCatcher\Twig\Components;

use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use PhpSentinel\BugCatcher\Entity\Record;
use PhpSentinel\BugCatcher\Entity\RecordStatus;
use PhpSentinel\BugCatcher\Entity\Role;
use PhpSentinel\BugCatcher\Repository\RecordRepository;
use PhpSentinel\BugCatcher\Repository\RecordRepositoryInterface;
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

	public function __construct(
		private readonly RecordRepository $recordRepo,
		private ManagerRegistry $registry,
		#[Autowire(param: 'dashboard_list_items')]
		private array           $classes
	) {}

	/**
	 * @return Record[]
	 */
	public function getLogs(): array {

		$em            = $this->registry->getManager();
		$classMetadata = $em->getClassMetadata(Record::class);
		$discriminatorMap = $classMetadata->discriminatorMap;
		$discriminatorMap = array_flip($discriminatorMap);
		$keys          = array_map(fn($class) => $discriminatorMap[$class]??null, $this->classes);

		$logs = $this->recordRepo->createQueryBuilder("record")
			->where("record.status = :status")
			->andWhere("record INSTANCE OF :class")
			->setParameter("status", $this->status)
			->setParameter("class", $keys)
			->orderBy("record.date", "DESC")
			->setMaxResults(100)
			->getQuery()->getResult();
		$grouped = [];
		foreach ($logs as $log) {
			$key = md5($log->getMessage());
			if (!array_key_exists($key, $grouped)) {
				$grouped[$key] = $log;
			} else {
				$grouped[$key]->setCount($grouped[$key]->getCount() + 1);
			}
		}

		return array_values($grouped);
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
			if (!($repo instanceof RecordRepositoryInterface)) {
				throw new Exception("Repository for Entity '{$class}' does not implement RecordRepositoryInterface");
			}
			$repo->setStatusOlderThan($date, 'resolved', $this->status);

		}

		return $this->redirectToRoute('bug_catcher.dashboard.index');
	}

	#[LiveAction]
	public function clearOne(
		#[LiveArg] Record                                                  $log,
		#[LiveArg] #[MapDateTime(format: "Y-m-d-H-i-s")] DateTimeImmutable $date,
		#[LiveArg] string $status
	) {
		$class = $log::class;
		$repo  = $this->registry->getRepository($class);
		if (!($repo instanceof RecordRepositoryInterface)) {
			throw new Exception("Repository for Entity '{$class}' does not implement RecordRepositoryInterface");
		}
		$repo->setStatus($log, $date, $status, $this->status);

		return $this->redirectToRoute('bug_catcher.dashboard.status', ['status' => $this->status]);
	}
}
