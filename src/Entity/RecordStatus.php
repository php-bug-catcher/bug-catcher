<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 5. 2024
 * Time: 19:36
 */
namespace PhpSentinel\BugCatcher\Entity;

enum RecordStatus: string {
	case NEW      = 'new';
	case ARCHIVED = 'archived';
	case RESOLVED = 'resolved';
}