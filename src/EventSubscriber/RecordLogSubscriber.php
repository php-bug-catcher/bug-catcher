<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 14. 7. 2024
 * Time: 21:01
 */
namespace BugCatcher\EventSubscriber;

use ApiPlatform\Symfony\EventListener\EventPriorities;
use BugCatcher\Entity\NotifierFavicon;
use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordLog;
use BugCatcher\Entity\RecordPing;
use BugCatcher\Enum\Importance;
use BugCatcher\Enum\RecordEventType;
use BugCatcher\Event\RecordEvent;
use BugCatcher\Event\RecordRecordedEvent;
use BugCatcher\Repository\RecordRepository;
use BugCatcher\Service\RecordLogWithholder;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class RecordLogSubscriber implements EventSubscriberInterface
{


	public function __construct(
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
			$this->dispatcher->dispatch(new RecordEvent($record, RecordEventType::CREATED, [$record->getProject()]));
		}
	}
}