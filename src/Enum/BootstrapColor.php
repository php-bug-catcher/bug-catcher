<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 12:06
 */
namespace PhpSentinel\BugCatcher\Enum;

enum BootstrapColor: string {
	case Primary   = "primary";
	case Secondary = "secondary";
	case Info      = "info";
	case Success   = "success";
	case Warning   = "warning";
	case Danger    = "danger";
	case Default   = "default";
}