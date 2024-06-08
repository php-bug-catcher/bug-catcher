<?php

namespace PhpSentinel\BugCatcher\Twig\Components;

use PhpSentinel\BugCatcher\Entity\NotifierFavicon;
use PhpSentinel\BugCatcher\Enum\Importance;
use PhpSentinel\BugCatcher\Repository\RecordLogRepository;
use PhpSentinel\BugCatcher\Service\DashboardStatus;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class LogCount extends AbsComponent {


	public function __construct(
		private readonly RecordLogRepository $recordRepo,
		private readonly DashboardStatus $status,
	) {}

	public function getCount(): int {
		$count = $this->recordRepo->count([
			"project" => $this->project,
			"status"  => 'new',
		]);
		$favIconNotifiers = $this->project->findNotifiers(NotifierFavicon::class, Importance::Low);
		$favIconNotifier = $favIconNotifiers->findFirst(function (int $i, NotifierFavicon $notifier) {
			return $notifier->getComponent() === 'LogCount';
		});
		if ($count && $favIconNotifier) {
			$this->status->incrementImportance(Importance::Medium, $count, $favIconNotifier->getThreshold());
		}

		return $count;
	}
}
