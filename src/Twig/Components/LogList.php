<?php

namespace PhpSentinel\BugCatcher\Twig\Components;

use Doctrine\Persistence\ManagerRegistry;
use PhpSentinel\BugCatcher\Entity\Record;
use PhpSentinel\BugCatcher\Entity\RecordStatus;
use PhpSentinel\BugCatcher\Entity\Role;
use PhpSentinel\BugCatcher\Repository\RecordLogRepository;
use DateTimeImmutable;
use PhpSentinel\BugCatcher\Repository\RecordRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\MapDateTime;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsLiveComponent]
final class LogList extends AbstractController
{
	use DefaultActionTrait;

	#[LiveProp]
	public RecordStatus $status;

	public function __construct(
		private readonly RecordRepository $recordRepo,
		private ManagerRegistry $registry
	) {}

	/**
	 * @return Record[]
	 */
	public function getLogs(): array {
		$logs = $this->recordRepo->findBy(["status" => $this->status], ['date' => 'DESC']);
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
	#[IsGranted('ROLE_DEVELOPER')]
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
			->getQuery()->getResult()
		;
		foreach ($rows as $row) {
			$this->registry->getRepository($row['0']::class)
				->setStatusOlderThan($date, RecordStatus::RESOLVED, $this->status);

		}

		return $this->redirectToRoute('bug_catcher.dashboard.index');
	}

	#[LiveAction]
	#[IsGranted('ROLE_DEVELOPER')]
	public function clearOne(
		#[LiveArg] Record                                                  $log,
		#[LiveArg] #[MapDateTime(format: "Y-m-d-H-i-s")] DateTimeImmutable $date,
		#[LiveArg] RecordStatus $status
	) {
		$this->registry->getRepository($log::class)->setStatus($log, $date, $status, $this->status);

		return $this->redirectToRoute('bug_catcher.dashboard.status', ['status' => $this->status->value]);
	}
}
