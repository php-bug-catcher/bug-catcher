<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 16:08
 */
namespace PhpSentinel\BugCatcher\EventSubscriber;

use PhpSentinel\BugCatcher\Enum\Importance;
use PhpSentinel\BugCatcher\Event\NotifyCalculateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class NotifyCalculateListener {
	public function __invoke(NotifyCalculateEvent $event): void {

		switch ($event->notifier->getComponent()) {
			case 'project-error-count':
				$event->status->incrementImportance(
					Importance::Normal,
					$event->recordEvent->sameProjectCount,
					$event->notifier->getThreshold()
				);
				break;
			case 'same-error-count':
				$event->status->incrementImportance(
					Importance::High,
					$event->recordEvent->sameRecordCount,
					$event->notifier->getThreshold()
				);
				break;
		}
	}


}