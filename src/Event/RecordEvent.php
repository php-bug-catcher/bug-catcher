<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 19:56
 */
namespace PhpSentinel\BugCatcher\Event;

use PhpSentinel\BugCatcher\Entity\Project;
use PhpSentinel\BugCatcher\Entity\Record;
use PhpSentinel\BugCatcher\Enum\RecordEventType;
use Symfony\Contracts\EventDispatcher\Event;

class RecordEvent extends Event {


	public function __construct(
		public readonly ?Record         $record,
		public readonly RecordEventType $type,
		/** @var Project[] */
		public readonly array           $projects
	) {}
}