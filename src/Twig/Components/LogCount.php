<?php

namespace PhpSentinel\BugCatcher\Twig\Components;

use PhpSentinel\BugCatcher\DTO\NotifierStatus;
use PhpSentinel\BugCatcher\Repository\NotifierRepository;
use PhpSentinel\BugCatcher\Repository\RecordLogRepository;
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
