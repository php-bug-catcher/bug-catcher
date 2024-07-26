<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 15:18
 */
namespace PhpSentinel\BugCatcher\EventSubscriber;

use PhpSentinel\BugCatcher\DTO\NotifierStatus;
use PhpSentinel\BugCatcher\Entity\NotifierFavicon;
use PhpSentinel\BugCatcher\Entity\Project;
use PhpSentinel\BugCatcher\Enum\Importance;
use PhpSentinel\BugCatcher\Event\NotifyCalculateEvent;
use PhpSentinel\BugCatcher\Event\NotifyEvent;
use PhpSentinel\BugCatcher\Event\RecordRecordedEvent;
use PhpSentinel\BugCatcher\Repository\NotifierRepository;
use PhpSentinel\BugCatcher\Repository\ProjectRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsEventListener]
class RecordRecordedNotifierListener {


	public function __construct(
		private readonly ProjectRepository        $projectRepo,
		private readonly NotifierRepository       $notifierRepo,
		private readonly EventDispatcherInterface $dispatcher
	) {}

	public function __invoke(RecordRecordedEvent $event): void {
		$status = new NotifierStatus();
		/** @var Project[] $projects */
		$projects = $this->projectRepo->getQBWith(enabled: true)->getQuery()->getResult();
		foreach ($projects as $project) {
			$state = $event->record->isError();
			foreach ($project->getNotifiers() as $notifier) {
				if (!$state && $this->notifierRepo->shouldNotify($notifier, false)) {
					$this->dispatcher->dispatch(new NotifyCalculateEvent($notifier, $status, $event));
				} else {
					$this->notifierRepo->stopNotify($notifier);
				}
			}
		}
		$importance = $status->getImportance();
		foreach ($projects as $project) {
			foreach ($project->getNotifiers() as $notifier) {
				if ($notifier->getMinimalImportance()->isHigherOrEqual($importance)) {
					$this->dispatcher->dispatch(new NotifyEvent($notifier, $importance));
				}
			}
		}
	}


}