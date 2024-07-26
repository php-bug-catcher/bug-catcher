<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 16:08
 */
namespace PhpSentinel\BugCatcher\EventSubscriber;

use PhpSentinel\BugCatcher\DTO\NotifierStatus;
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

		$status    = new NotifierStatus();
		$notifiers = $this->notifierRepo->findAll();
		foreach ($notifiers as $notifier) {
			$this->dispatcher->dispatch(new NotifyCalculateEvent(
				$notifier,
				$status,
			));
		}
		$importance = $status->getImportance();
		foreach ($notifiers as $notifier) {
			if ($this->notifierRepo->shouldNotify($notifier, false)) {
				if ($notifier->getMinimalImportance()->isHigherOrEqual($importance)) {
					$this->dispatcher->dispatch(new NotifyEvent($notifier, $importance));
				}
			} else {
				$this->notifierRepo->stopNotify($notifier);
			}
		}
	}


}