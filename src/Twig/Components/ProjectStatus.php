<?php

namespace PhpSentinel\BugCatcher\Twig\Components;

use PhpSentinel\BugCatcher\DTO\NotifierStatus;
use PhpSentinel\BugCatcher\Repository\NotifierRepository;
use PhpSentinel\BugCatcher\Repository\RecordPingRepository;
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

		return $ping?->isError()??false;
	}
}
