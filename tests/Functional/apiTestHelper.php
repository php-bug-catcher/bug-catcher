<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 10. 4. 2023
 * Time: 10:09
 */

namespace BugCatcher\Tests\Functional;

use ApiPlatform\Api\IriConverterInterface;
use JetBrains\PhpStorm\ArrayShape;
use BugCatcher\Entity\User;
use Zenstruck\Browser\KernelBrowser;
use Zenstruck\Browser\Test\HasBrowser;
use Zenstruck\Foundry\Test\ResetDatabase;
use function Zenstruck\Foundry\faker;

trait apiTestHelper {

	use ResetDatabase;
	use HasBrowser {
		HasBrowser::browser as baseKernelBrowser;
	}


	protected function setUp(): void {
		$kernel = self::bootKernel();


	}

	#[ArrayShape([KernelBrowser::class, User::class])]
	protected function browser($roles = []): array {
		return [
			$this->baseKernelBrowser()
				->setDefaultHttpOptions([
					'headers' => [
						'Accept' => 'application/ld+json',
//						'Authorization' => 'Bearer ' . $token->getToken(),
					],
				]),
//			$user->object(),
		];
	}
}