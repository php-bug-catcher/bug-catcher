<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 16:08
 */
namespace BugCatcher\EventSubscriber;

use BugCatcher\Entity\NotifierFavicon;
use BugCatcher\Entity\NotifierSound;
use BugCatcher\Event\NotifyAfterEvent;
use BugCatcher\Service\DashboardImportance;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class NotifyAfterListener
{
	public function __construct(
		private readonly DashboardImportance $importance
	) {}

	public function __invoke(NotifyAfterEvent $event): void {
		if ($event->notifier instanceof NotifierFavicon || $event->notifier instanceof NotifierSound) {
			$this->importance->save($event->notifier::class);
		}
	}


}