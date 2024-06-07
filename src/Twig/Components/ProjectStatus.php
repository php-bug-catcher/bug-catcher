<?php

namespace PhpSentinel\BugCatcher\Twig\Components;

use PhpSentinel\BugCatcher\Repository\RecordPingRepository;
use PhpSentinel\BugCatcher\Service\DashboardStatus;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class ProjectStatus extends AbsComponent {

	public function __construct(
		private readonly RecordPingRepository $recordRepo,
		private readonly DashboardStatus $status,
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
		if (!$state) {
			$this->status->incrementImportance(10, 4);
		}

		return $state;
	}
}
