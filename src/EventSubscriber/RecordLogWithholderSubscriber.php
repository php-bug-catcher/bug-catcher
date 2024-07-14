<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 14. 7. 2024
 * Time: 21:01
 */
namespace PhpSentinel\BugCatcher\EventSubscriber;

use ApiPlatform\Symfony\EventListener\EventPriorities;
use PhpSentinel\BugCatcher\Entity\RecordLog;
use PhpSentinel\BugCatcher\Service\RecordLogWithholder;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RecordLogWithholderSubscriber implements EventSubscriberInterface {


	public function __construct(
		private readonly RecordLogWithholder $withholder
	) {}

	public static function getSubscribedEvents() {
		return [
			KernelEvents::VIEW => ['process', EventPriorities::POST_WRITE],
		];
	}

	public function process(ViewEvent $event): void {
		$record = $event->getControllerResult();
		$method = $event->getRequest()->getMethod();

		if (!$record instanceof RecordLog || Request::METHOD_POST !== $method) {
			return;
		}
		$this->withholder->process($record);
	}
}