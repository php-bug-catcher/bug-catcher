<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 16:21
 */
namespace PhpSentinel\BugCatcher\EventSubscriber\Notifiers;

use PhpSentinel\BugCatcher\Entity\NotifierEmail;
use PhpSentinel\BugCatcher\Entity\NotifierFavicon;
use PhpSentinel\BugCatcher\Entity\NotifierSound;
use PhpSentinel\BugCatcher\Enum\Importance;
use PhpSentinel\BugCatcher\Event\NotifyEvent;
use PhpSentinel\BugCatcher\Service\DashboardImportance;
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