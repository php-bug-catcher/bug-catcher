<?php

namespace BugCatcher\Twig\Components;

use BugCatcher\DTO\NotifierStatus;
use BugCatcher\Repository\NotifierRepository;
use BugCatcher\Repository\RecordLogRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class LogCount extends AbsComponent {


	public function __construct(
		private readonly RecordLogRepository $recordRepo,
	) {}

	public function getCount(): int {
		$count           = $this->recordRepo->count([
			"project" => $this->project,
			"status"  => 'new',
		]);

		return $count;
	}
}
