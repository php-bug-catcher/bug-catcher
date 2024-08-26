<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 16:04
 */
namespace BugCatcher\Event;

use BugCatcher\DTO\NotifierStatus;
use BugCatcher\Entity\Notifier;
use BugCatcher\Entity\Project;
use Symfony\Contracts\EventDispatcher\Event;

final class NotifyCalculateEvent extends Event
{

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