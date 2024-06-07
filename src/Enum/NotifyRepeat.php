<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 20:02
 */
namespace PhpSentinel\BugCatcher\Enum;

enum NotifyRepeat: string {
	case Once           = 'once';
	case OnPeriodTime   = 'on_period_time';
	case OnPeriodReload = 'on_period_reload';
}
