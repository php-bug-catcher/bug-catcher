<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 16:17
 */
namespace BugCatcher\Event;

use BugCatcher\Entity\Notifier;
use BugCatcher\Enum\Importance;
use Symfony\Contracts\EventDispatcher\Event;

final class NotifyBeforeEvent extends Event
{


	public function __construct(
		public readonly Notifier $notifier,
	) {}
}