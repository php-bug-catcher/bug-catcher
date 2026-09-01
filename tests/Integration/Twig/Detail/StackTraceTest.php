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

    //use ResetDatabase;
	use Factories;
	use GetStackTrace;

	public function testFailedDeserialize() {
		$record   = RecordLogTraceFactory::createOne([
			"stackTrace" => "not serialized",
		]);
		$rendered = $this->renderTwigComponent('Detail:StackTrace', ['record' => $record]);
		$this->assertSame("Unable to unserialize stacktrace (line 0)", $rendered->crawler()->filter("button.accordion-button")->text());
	}

	public function testNormalizePaths() {

		$record = RecordLogTraceFactory::createOne([
			"stackTrace" => $this->getStackTrace(),
		]);

		$rendered = $this->mountTwigComponent('Detail:StackTrace', ['record' => $record]);
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

	/**
	 * The gutter number comes from `counter-set: listing N` on the <code> element, which the
	 * stylesheet then bumps with `counter-increment: listing` on that same element. Since CSS
	 * applies set before increment, the value has to be seeded one below the real line number.
	 */
	public function testCodeGutterIsSeededOneBelowTheLineNumber() {
		$record = RecordLogTraceFactory::createOne([
			"stackTrace" => serialize($this->codeFrames()),
		]);

		$rendered = $this->renderTwigComponent('Detail:StackTrace', ['record' => $record]);
		$styles   = $rendered->crawler()->filter("div.code code")->each(fn($node) => $node->attr('style'));

		$this->assertSame([
			'counter-set: listing 56;',
			'counter-set: listing 57;',
			'counter-set: listing 58;',
		], $styles);
	}

	public function testFrameLabelIsRendered() {
		$record = RecordLogTraceFactory::createOne([
			"stackTrace" => serialize($this->codeFrames()),
		]);

		$rendered = $this->renderTwigComponent('Detail:StackTrace', ['record' => $record]);
		$buttons  = $rendered->crawler()->filter("button.accordion-button")->each(fn($node) => $node->text());

		$this->assertStringContainsString('RuntimeException: boom', $buttons[0]);
		$this->assertStringContainsString('GponRadius->proceed(Object)', $buttons[1]);
	}

	/**
	 * @return Codeframe[]
	 */
	private function codeFrames(): array {
		return [
			new Codeframe('/app/src/Cpe/GponRadius.php', 58, [
				57 => "\$this->queries = [];\n",
				58 => "\$this->proceed(\$ont);\n",
				59 => "\$this->save();\n",
			], 'RuntimeException: boom'),
			new Codeframe('/app/src/Runner.php', 12, [], 'GponRadius->proceed(Object)'),
		];
	}

}
