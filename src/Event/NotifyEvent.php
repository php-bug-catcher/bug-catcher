<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 16:17
 */
namespace PhpSentinel\BugCatcher\Event;

use PhpSentinel\BugCatcher\Entity\Notifier;
use PhpSentinel\BugCatcher\Entity\Project;
use PhpSentinel\BugCatcher\Enum\Importance;
use Symfony\Contracts\EventDispatcher\Event;

class NotifyEvent extends Event {


	public function __construct(
		public readonly Notifier   $notifier,
		public readonly Importance $importance,
		public readonly Project    $project
	) {}
}