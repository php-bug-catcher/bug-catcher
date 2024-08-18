<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 8. 2024
 * Time: 14:41
 */
namespace BugCatcher\Tests\App\EventSubscriber;

use BugCatcher\Entity\NotifierEmail;
use BugCatcher\Event\NotifyEvent;
use BugCatcher\Tests\App\Service\ListenerIsCalled;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class EmailNotifyListener {


	public function __construct(private readonly ListenerIsCalled $listenerIsCalled) {}

	public function __invoke(NotifyEvent $event): void {
		if ($event->notifier instanceof NotifierEmail) {
			$this->listenerIsCalled->call(self::class);
		}
	}
}