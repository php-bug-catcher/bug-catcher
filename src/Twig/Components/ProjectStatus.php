<?php

namespace BugCatcher\Twig\Components;

use BugCatcher\DTO\NotifierStatus;
use BugCatcher\Repository\NotifierRepository;
use BugCatcher\Repository\RecordPingRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class ProjectStatus extends AbsComponent {

	public function __construct(
		private readonly RecordPingRepository $recordRepo,
	) {}

	public function getLastStatus(): string {
		if ($this->project->getPingCollector() == 'none') {
			return true;
		}
		$ping = $this->recordRepo->getLastRecord($this->project);

		return (!$ping?->isError()) ?? false;
	}
}
