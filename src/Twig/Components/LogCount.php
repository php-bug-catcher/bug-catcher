<?php

namespace PhpSentinel\BugCatcher\Twig\Components;

use PhpSentinel\BugCatcher\Entity\RecordStatus;
use PhpSentinel\BugCatcher\Repository\RecordLogRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class LogCount extends AbsComponent
{


	public function __construct(
		private readonly RecordLogRepository $recordRepo
	) {}

	public function getCount():int {
		return $this->recordRepo->count([
			"project"=>$this->project,
			"status" => RecordStatus::NEW,
		]);
	}
}
