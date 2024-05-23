<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 23. 5. 2024
 * Time: 10:28
 */
namespace App\Tests\Functional;

use Zenstruck\Browser\KernelBrowser;
use Zenstruck\Browser\Test\HasBrowser;
use Zenstruck\Foundry\Test\ResetDatabase;

trait ApiTestHelper {

	use ResetDatabase;
	use HasBrowser {
		browser as baseKernelBrowser;
	}

	protected function setUp(): void {
		$kernel = self::bootKernel();
	}

	protected function browser(): KernelBrowser {
		return $this->baseKernelBrowser()
			->setDefaultHttpOptions([
				'headers' => [
					'Accept' => 'application/ld+json',
//						'Authorization' => 'Bearer ' . $token->getToken(),
				],
			]);
	}
}