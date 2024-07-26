<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 16:04
 */
namespace PhpSentinel\BugCatcher\Event;

use PhpSentinel\BugCatcher\DTO\NotifierStatus;
use PhpSentinel\BugCatcher\Entity\Notifier;
use PhpSentinel\BugCatcher\Entity\Project;
use Symfony\Contracts\EventDispatcher\Event;

class NotifyCalculateEvent extends Event {

	/**
	 * @var NotifierStatus[]
	 */
	private array $statuses = [];

	public function __construct(
		public readonly Notifier $notifier,
	) {}

	public function addStatus(NotifierStatus $status): void {
		$this->statuses[] = $status;
	}

	public function getStatuses(): array {
		return $this->statuses;
	}

}