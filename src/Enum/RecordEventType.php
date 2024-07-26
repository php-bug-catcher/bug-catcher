<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 19:57
 */
namespace PhpSentinel\BugCatcher\Enum;

enum RecordEventType {

	case CREATED;
	case UPDATED;
	case BATCH_UPDATED;
}
