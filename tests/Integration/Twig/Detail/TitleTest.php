<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 7. 2024
 * Time: 14:59
 */

namespace BugCatcher\Tests\Integration\Twig\Detail;

use BugCatcher\Tests\App\Factory\RecordLogTraceFactory;
use BugCatcher\Tests\App\KernelTestCase;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class TitleTest extends KernelTestCase {
	use InteractsWithTwigComponents;

    //use ResetDatabase;
	use Factories;

	public function testTitle() {
		$record   = RecordLogTraceFactory::createOne([
			"message" => "Test title",
		]);
		$rendered = $this->renderTwigComponent('Detail:Title', ['record' => $record]);
		$this->assertSame("Test title", $rendered->crawler()->filter("h4")->text());
	}
}
