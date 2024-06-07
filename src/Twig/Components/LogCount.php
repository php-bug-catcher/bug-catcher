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
		$favIconNotifiers = $this->project->getNotifier(NotifierFavicon::class, Importance::Low);
		if ($count && $favIconNotifiers) {
			$this->status->incrementImportance(Importance::Medium, $count, $favIconNotifiers->getImportance());
		}

		return $count;
	}
}
