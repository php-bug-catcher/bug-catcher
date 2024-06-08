<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 20:02
 */
namespace PhpSentinel\BugCatcher\Enum;

enum NotifyRepeat: string {
	case None             = 'none';
	case FrequencyRecords = 'frequency_records';
	case PeriodTime       = 'period_time';
}
