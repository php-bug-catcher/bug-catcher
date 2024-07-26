<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 16:08
 */
namespace PhpSentinel\BugCatcher\EventSubscriber;

use PhpSentinel\BugCatcher\Entity\NotifierFavicon;
use PhpSentinel\BugCatcher\Entity\NotifierSound;
use PhpSentinel\BugCatcher\Event\NotifyAfterEvent;
use PhpSentinel\BugCatcher\Service\DashboardImportance;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class NotifyAfterListener {
	public function __construct(
		private readonly DashboardImportance $importance
	) {}

	public function __invoke(NotifyAfterEvent $event): void {
		if ($event->notifier instanceof NotifierFavicon || $event->notifier instanceof NotifierSound) {
			$this->importance->save($event->notifier::class);
		}
	}


}