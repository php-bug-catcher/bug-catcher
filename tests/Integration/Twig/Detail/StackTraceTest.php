<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 7. 2024
 * Time: 14:24
 */
namespace BugCatcher\Tests\Integration\Twig\Detail;


use BugCatcher\Tests\App\Factory\RecordLogTraceFactory;
use Exception;
use Kregel\ExceptionProbe\Codeframe;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Integration\Trait\GetStackTrace;
use BugCatcher\Twig\Components\Detail\StackTrace;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class StackTraceTest extends KernelTestCase {
	use InteractsWithTwigComponents;
	use ResetDatabase;
	use Factories;
	use GetStackTrace;

	public function testFailedDeserialize() {
		$record   = RecordLogTraceFactory::createOne([
			"stackTrace" => "not serialized",
		]);
		$rendered = $this->renderTwigComponent('StackTrace', ['record' => $record]);
		$this->assertSame("Unable to unserialize stacktrace (line 0)", $rendered->crawler()->filter("button.accordion-button")->text());
	}

	public function testNormalizePaths() {

		$record = RecordLogTraceFactory::createOne([
			"stackTrace" => $this->getStackTrace(),
		]);

		$rendered = $this->mountTwigComponent('StackTrace', ['record' => $record]);
		$this->assertInstanceOf(StackTrace::class, $rendered);

		$first = null;
		foreach ($rendered->trace as $pos => $item) {
			if (str_starts_with($item->getFile(), '/vendor/')) {
				continue;
			}
			if (str_starts_with($item->getFile(), '/tests/')) {
				if ($first === null) {
					$first = $pos;
				}
				continue;
			}
			$this->assertSame(false, array_map(fn(Codeframe $item) => $item->getFile(), $rendered->trace));
		}
		$this->assertSame($first, $rendered->opened);
	}


}
