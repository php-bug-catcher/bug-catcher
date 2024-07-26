<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 15:15
 */
namespace PhpSentinel\BugCatcher\Event;

use PhpSentinel\BugCatcher\Entity\Record;
use Symfony\Contracts\EventDispatcher\Event;

class RecordRecordedEvent extends Event {


	public function __construct(
		public readonly Record $record,
		public readonly int    $sameRecordCount,
		public readonly int    $sameProjectCount,
	) {}
}