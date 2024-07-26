<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 14. 7. 2024
 * Time: 21:01
 */
namespace PhpSentinel\BugCatcher\EventSubscriber;

use ApiPlatform\Symfony\EventListener\EventPriorities;
use PhpSentinel\BugCatcher\Entity\NotifierFavicon;
use PhpSentinel\BugCatcher\Entity\Record;
use PhpSentinel\BugCatcher\Entity\RecordLog;
use PhpSentinel\BugCatcher\Entity\RecordPing;
use PhpSentinel\BugCatcher\Enum\Importance;
use PhpSentinel\BugCatcher\Event\RecordRecordedEvent;
use PhpSentinel\BugCatcher\Repository\RecordRepository;
use PhpSentinel\BugCatcher\Service\RecordLogWithholder;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RecordLogSubscriber implements EventSubscriberInterface {


	public function __construct(
		private readonly RecordRepository         $recordRepo,
		private readonly RecordLogWithholder      $withholder,
		private readonly EventDispatcherInterface $dispatcher
	) {}

	public static function getSubscribedEvents() {
		return [
			KernelEvents::VIEW => ['process', EventPriorities::POST_WRITE],
		];
	}

	public function process(ViewEvent $event): void {
		$record = $event->getControllerResult();
		$method = $event->getRequest()->getMethod();

		if ($record instanceof RecordLog && Request::METHOD_POST == $method) {
			$this->withholder->process($record);
		}
		if ($record instanceof Record && Request::METHOD_POST == $method) {
			if ($record->getStatus() == "resolved") {
				$count            = 0;
				$sameProjectCount = 0;
			} else {
				$count            = $this->recordRepo->count([
					"hash"    => $record->getHash(),
					"project" => $record->getProject(),
					"status"  => $record->getStatus(),
				]);
				$sameProjectCount = $this->recordRepo->count([
					"project" => $record->getProject(),
					"status"  => $record->getStatus(),
				]);
			}
			$this->dispatcher->dispatch(new RecordRecordedEvent(
				$record,
				$count,
				$sameProjectCount
			));
		}
	}
}