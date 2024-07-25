<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 7. 2024
 * Time: 13:01
 */
namespace PhpSentinel\BugCatcher\Tests\Integration\Twig\Detail;

use PhpSentinel\BugCatcher\Factory\RecordLogFactory;
use PhpSentinel\BugCatcher\Tests\Integration\KernelTestCase;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class HeaderTest extends KernelTestCase {
	use InteractsWithTwigComponents;
	use ResetDatabase;
	use Factories;

	public function testHeader(): void {

		$record = RecordLogFactory::createOne([
			"requestUri" => "test-url",
		]);

		$rendered = $this->renderTwigComponent('Header', ['record' => $record]);
		$this->assertSame("test-url", $rendered->crawler()->filter('.fs-4')->text());
	}
}