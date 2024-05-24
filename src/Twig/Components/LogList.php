<?php

namespace App\Twig\Components;

use App\Entity\LogRecord;
use App\Entity\Role;
use App\Repository\LogRecordRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\MapDateTime;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsLiveComponent]
final class LogList extends AbstractController
{
	use DefaultActionTrait;
	public function __construct(
		private readonly LogRecordRepository $recordRepo
	) {}

	/**
	 * @return LogRecord[]
	 */
	public function getLogs(): array {
		$logs    = $this->recordRepo->findBy(["checked" => false], ['date' => 'DESC']);
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
	#[IsGranted(Role::ROLE_DEVELOPER->value)]
	public function clearAll(#[LiveArg] #[MapDateTime(format: "Y-m-d-H-i-s")] DateTimeImmutable $date) {
		$this->recordRepo->checkOlderThan($date);

		return $this->redirectToRoute('app.dashboard');
	}

	#[LiveAction]
	#[IsGranted(Role::ROLE_DEVELOPER->value)]
	public function clearOne(#[LiveArg] LogRecord $log, #[LiveArg] #[MapDateTime(format: "Y-m-d-H-i-s")] DateTimeImmutable $date) {
		$this->recordRepo->check($log, $date);

		return $this->redirectToRoute('app.dashboard');
	}
}
