<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 16:17
 */
namespace BugCatcher\Event;

use BugCatcher\Entity\Notifier;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\Importance;
use Symfony\Contracts\EventDispatcher\Event;

final class NotifyEvent extends Event
{


	public function __construct(
		public readonly Notifier   $notifier,
		public readonly Importance $importance,
		public readonly Project    $project
	) {}
}