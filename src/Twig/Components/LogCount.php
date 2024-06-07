<?php

namespace PhpSentinel\BugCatcher\Twig\Components;

use PhpSentinel\BugCatcher\Repository\RecordLogRepository;
use PhpSentinel\BugCatcher\Service\FaviconStatus;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class LogCount extends AbsComponent {


	public function __construct(
		private readonly RecordLogRepository $recordRepo,
		private readonly FaviconStatus       $status,
	) {}

	public function getCount(): int {
		$count = $this->recordRepo->count([
			"project" => $this->project,
			"status"  => 'new',
		]);
		if ($count) {
			$this->status->incrementImportance(2, $count, 80);
		}

		return $count;
	}
}
