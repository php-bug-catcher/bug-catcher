<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 16:08
 */
namespace PhpSentinel\BugCatcher\EventSubscriber;

use PhpSentinel\BugCatcher\Event\NotifyAfterEvent;
use PhpSentinel\BugCatcher\Event\NotifyBeforeEvent;
use PhpSentinel\BugCatcher\Event\NotifyCalculateEvent;
use PhpSentinel\BugCatcher\Event\NotifyEvent;
use PhpSentinel\BugCatcher\Event\RecordEvent;
use PhpSentinel\BugCatcher\Repository\NotifierRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsEventListener]
class RecordListener {
	public function __construct(
		private readonly NotifierRepository       $notifierRepo,
		private readonly EventDispatcherInterface $dispatcher
	) {}

	public function __invoke(RecordEvent $event): void {

		$notifiers = $this->notifierRepo->findAll();
		/** @var NotifyCalculateEvent[] $events */
		$events = [];
		foreach ($notifiers as $notifier) {
			$event    = new NotifyCalculateEvent($notifier);
			$events[] = $event;
			$this->dispatcher->dispatch($event);
		}
		foreach ($events as $event) {
			$this->dispatcher->dispatch(new NotifyBeforeEvent($event->notifier));
		}
		foreach ($events as $event) {
			$notifier = $event->notifier;
			if ($this->notifierRepo->shouldNotify($notifier, false)) {
				foreach ($event->getStatuses() as $status) {
					$importance = $status->getImportance();
					if ($notifier->getMinimalImportance()->isHigherOrEqual($importance)) {
						$this->dispatcher->dispatch(new NotifyEvent($notifier, $importance, $status->project));
					}
				}
			} else {
				$this->notifierRepo->stopNotify($notifier);
			}
		}
		foreach ($events as $event) {
			$this->dispatcher->dispatch(new NotifyAfterEvent($event->notifier));
		}
	}


}