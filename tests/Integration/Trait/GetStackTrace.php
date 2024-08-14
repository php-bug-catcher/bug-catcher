<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 14. 8. 2024
 * Time: 8:35
 */
namespace PhpSentinel\BugCatcher\Tests\Integration\Trait;

use Exception;
use Kregel\ExceptionProbe\Stacktrace;

trait GetStackTrace {
	private function getStackTrace(): string {
		$stacktrace = new Stacktrace();
		try {
			throw new Exception();
		} catch (Exception $e) {
			return serialize($stacktrace->parse($e->getTraceAsString()));
		}
	}
}