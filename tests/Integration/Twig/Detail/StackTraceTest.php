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
		$this->assertSame("Unable to unserialize stacktrace (line 0)", $rendered->crawler()->filter('[data-testid="frame-trigger"]')->text());
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
		$buttons  = $rendered->crawler()->filter('[data-testid="frame-trigger"]')->each(fn($node) => $node->text());

		$this->assertStringContainsString('RuntimeException: boom', $buttons[0]);
		$this->assertStringContainsString('GponRadius->proceed(Object)', $buttons[1]);
	}

	/**
	 * The copied path has to be the project relative one, not the absolute path of the production
	 * server the record was reported from - that directory does not exist on the developer machine.
	 */
	public function testCopyButtonCarriesTheProjectRelativePathWithLine() {
		$record = RecordLogTraceFactory::createOne([
			"stackTrace" => serialize($this->codeFrames()),
		]);

		$rendered = $this->renderTwigComponent('Detail:StackTrace', ['record' => $record]);
		$copied   = $rendered->crawler()
			->filter("[data-controller='clipboard']")
			->each(fn($node) => $node->attr('data-clipboard-text-value'));

		$this->assertSame([
			'Cpe/GponRadius.php:58',
			'Runner.php:12',
		], $copied);
	}

	public function testNoCopyButtonWhenTheTraceCouldNotBeRead() {
		$record = RecordLogTraceFactory::createOne([
			"stackTrace" => "not serialized",
		]);

		$rendered = $this->renderTwigComponent('Detail:StackTrace', ['record' => $record]);

		$this->assertCount(0, $rendered->crawler()->filter("[data-controller='clipboard']"));
	}

	/**
	 * The common prefix is cut on a directory boundary, otherwise sibling files such as
	 * `/app/src/Runner.php` and `/app/src/RunnerFactory.php` would lose part of their name.
	 */
	public function testPrefixIsStrippedOnlyUpToADirectoryBoundary() {
		$record = RecordLogTraceFactory::createOne([
			"stackTrace" => serialize([
				new Codeframe('/app/src/Runner.php', 12, [], ''),
				new Codeframe('/app/src/RunnerFactory.php', 30, [], ''),
			]),
		]);

		$rendered = $this->mountTwigComponent('Detail:StackTrace', ['record' => $record]);

		$this->assertSame(
			['/Runner.php', '/RunnerFactory.php'],
			array_map(fn(Codeframe $item) => $item->getFile(), $rendered->trace)
		);
	}

	/**
	 * A reporter may append frames that hold a label instead of a path. Those must not collapse the
	 * common prefix, otherwise every other frame is left with the full deploy path of the server.
	 */
	public function testFramesWithoutAPathDoNotDisableShortening() {
		$record = RecordLogTraceFactory::createOne([
			"stackTrace" => serialize([
				new Codeframe('Caused by: RuntimeException', 0, [], ''),
				new Codeframe('/var/www2/flexi.sk/releases/28/src/Controller/MailsController.php', 161, [], ''),
				new Codeframe('/var/www2/flexi.sk/releases/28/vendor/symfony/http-kernel/HttpKernel.php', 183, [], ''),
			]),
		]);

		$rendered = $this->mountTwigComponent('Detail:StackTrace', ['record' => $record]);

		$this->assertSame([
			'Caused by: RuntimeException',
			'/src/Controller/MailsController.php',
			'/vendor/symfony/http-kernel/HttpKernel.php',
		], array_map(fn(Codeframe $item) => $item->getFile(), $rendered->trace));
	}

	/**
	 * The frames do not have to be a zero indexed list - a reporter may hand over whatever keys its
	 * own filtering left behind.
	 */
	public function testShorteningDoesNotDependOnZeroIndexedFrames() {
		$record = RecordLogTraceFactory::createOne([
			"stackTrace" => serialize([
				3 => new Codeframe('/app/src/Runner.php', 12, [], ''),
				7 => new Codeframe('/app/vendor/symfony/Kernel.php', 30, [], ''),
			]),
		]);

		$rendered = $this->mountTwigComponent('Detail:StackTrace', ['record' => $record]);

		$this->assertSame([
			'/src/Runner.php',
			'/vendor/symfony/Kernel.php',
		], array_values(array_map(fn(Codeframe $item) => $item->getFile(), $rendered->trace)));
	}

	public function testWindowsPathsAreShortenedToo() {
		$record = RecordLogTraceFactory::createOne([
			"stackTrace" => serialize([
				new Codeframe('C:\\projects\\app\\src\\Runner.php', 12, [], ''),
				new Codeframe('C:\\projects\\app\\vendor\\symfony\\Kernel.php', 30, [], ''),
			]),
		]);

		$rendered = $this->mountTwigComponent('Detail:StackTrace', ['record' => $record]);

		$this->assertSame([
			'\\src\\Runner.php',
			'\\vendor\\symfony\\Kernel.php',
		], array_map(fn(Codeframe $item) => $item->getFile(), $rendered->trace));
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
