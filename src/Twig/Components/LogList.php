<?php

namespace PhpSentinel\BugCatcher\Twig\Components;

use PhpSentinel\BugCatcher\Entity\Record;
use PhpSentinel\BugCatcher\Entity\RecordStatus;
use PhpSentinel\BugCatcher\Entity\Role;
use PhpSentinel\BugCatcher\Repository\RecordLogRepository;
use DateTimeImmutable;
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
		private readonly RecordLogRepository $recordRepo
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
		$this->recordRepo->setStatusOlderThan($date, RecordStatus::RESOLVED);

		return $this->redirectToRoute('app.dashboard');
	}

	#[LiveAction]
	#[IsGranted('ROLE_DEVELOPER')]
	public function clearOne(
		#[LiveArg] Record                                                  $log,
		#[LiveArg] #[MapDateTime(format: "Y-m-d-H-i-s")] DateTimeImmutable $date,
		#[LiveArg] RecordStatus $status
	) {
		$this->recordRepo->setStatus($log, $date, $status);

		return $this->redirectToRoute('app.dashboard.status', ['status' => $this->status->value]);
	}
}
