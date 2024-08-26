<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 19:56
 */
namespace BugCatcher\Event;

use BugCatcher\Entity\Project;
use BugCatcher\Entity\Record;
use BugCatcher\Enum\RecordEventType;
use Symfony\Contracts\EventDispatcher\Event;

final class RecordEvent extends Event
{


	public function __construct(
		public readonly ?Record         $record,
		public readonly RecordEventType $type,
		/** @var Project[] */
		public readonly array           $projects
	) {}
}