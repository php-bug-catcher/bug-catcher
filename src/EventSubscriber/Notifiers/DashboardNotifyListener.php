<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 16:21
 */
namespace PhpSentinel\BugCatcher\EventSubscriber\Notifiers;

use PhpSentinel\BugCatcher\Event\NotifyEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class DashboardNotifyListener {
	public function __invoke(NotifyEvent $event): void {}

}