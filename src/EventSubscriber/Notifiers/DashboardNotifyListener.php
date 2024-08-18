<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 16:21
 */
namespace BugCatcher\EventSubscriber\Notifiers;

use BugCatcher\Entity\NotifierEmail;
use BugCatcher\Entity\NotifierFavicon;
use BugCatcher\Entity\NotifierSound;
use BugCatcher\Enum\Importance;
use BugCatcher\Event\NotifyEvent;
use BugCatcher\Service\DashboardImportance;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class DashboardNotifyListener {


	public function __construct(
		private readonly DashboardImportance $importance
	) {}

	public function __invoke(NotifyEvent $event): void {
		if ($event->notifier instanceof NotifierFavicon || $event->notifier instanceof NotifierSound) {
			$this->importance->upgradeHigher($event->notifier::class, $event->importance, $event->notifier);
		}
	}

}