<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 23. 5. 2024
 * Time: 20:53
 */
namespace PhpSentinel\BugCatcher\Tests\Integration;

use Symfony\Component\HttpKernel\KernelInterface;

class KernelTestCase extends \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase {

	protected static function bootKernel(array $options = []): KernelInterface {
		static::ensureKernelShutdown();

		$kernel = new Kernel($options);
		$kernel->boot();
		static::$kernel = $kernel;
		static::$booted = true;

		return static::$kernel;
	}
}