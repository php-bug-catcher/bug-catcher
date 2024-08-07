<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 8. 2024
 * Time: 14:41
 */
namespace PhpSentinel\BugCatcher\Tests\App\EventSubscriber;

use PhpSentinel\BugCatcher\Entity\NotifierEmail;
use PhpSentinel\BugCatcher\Event\NotifyEvent;
use PhpSentinel\BugCatcher\Tests\App\Service\ListenerIsCalled;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class EmailNotifyListener {


	public function __construct(private readonly ListenerIsCalled $listenerIsCalled) {}

	public function __invoke(NotifyEvent $event): void {
		if ($event->notifier instanceof NotifierEmail) {
			dump("EmailNotifyListener");
			$this->listenerIsCalled->call(self::class);
		}
	}
}