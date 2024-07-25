<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 7. 2024
 * Time: 14:24
 */
namespace PhpSentinel\BugCatcher\Tests\Integration\Twig\Detail;


use Exception;
use PhpSentinel\BugCatcher\Factory\RecordLogTraceFactory;
use PhpSentinel\BugCatcher\Tests\Integration\KernelTestCase;
use PhpSentinel\BugCatcher\Twig\Components\Detail\StackTrace;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class StackTraceTest extends KernelTestCase {
	use InteractsWithTwigComponents;
	use ResetDatabase;
	use Factories;

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
			$this->assertSame(false, $item);
		}
		$this->assertSame($first, $rendered->opened);
	}

	private function getStackTrace(): string {
		$stacktrace = new \Kregel\ExceptionProbe\Stacktrace();
		try {
			throw new Exception();
		} catch (Exception $e) {
			return serialize($stacktrace->parse($e->getTraceAsString()));
		}
	}
}
