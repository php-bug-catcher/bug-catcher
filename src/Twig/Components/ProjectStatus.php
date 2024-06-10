<?php

namespace PhpSentinel\BugCatcher\Twig\Components;

use PhpSentinel\BugCatcher\Entity\NotifierFavicon;
use PhpSentinel\BugCatcher\Enum\Importance;
use PhpSentinel\BugCatcher\Repository\NotifierRepository;
use PhpSentinel\BugCatcher\Repository\RecordPingRepository;
use PhpSentinel\BugCatcher\Service\DashboardStatus;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class ProjectStatus extends AbsComponent {

	public function __construct(
		private readonly RecordPingRepository $recordRepo,
		private readonly DashboardStatus $status,
		private readonly NotifierRepository $notifierRepo
	) {}

	public function getLastStatus(): string {
		if ($this->project->getPingCollector() == 'none') {
			return true;
		}
		$ping = $this->recordRepo->findOneBy([
			"project" => $this->project,
		], [
			"date" => "DESC",
		]);

		$state = $ping?->getStatusCode() == Response::HTTP_OK;
		$favIconNotifiers = $this->project->findNotifiers(NotifierFavicon::class, Importance::Low);
		$favIconNotifier = $favIconNotifiers->findFirst(function (int $i, NotifierFavicon $notifier) {
			return $notifier->getComponent() === 'ProjectStatus';
		});
		if ($favIconNotifier) {
			if (!$state && $this->notifierRepo->shouldNotify($favIconNotifier, false)) {
				$this->status->incrementImportance(Importance::High, 1, $favIconNotifier->getThreshold());
			} else {
				$this->notifierRepo->stopNotify($favIconNotifier);
			}
		}

		return $state;
	}
}
