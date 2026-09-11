<?php

namespace BugCatcher\Tests\Unit\Service\StackTrace;

use BugCatcher\Service\StackTrace\MalformedStackTraceException;
use BugCatcher\Service\StackTrace\StackTraceParser;
use Kregel\ExceptionProbe\Codeframe;
use PHPUnit\Framework\TestCase;

class StackTraceParserTest extends TestCase {

	private StackTraceParser $parser;

	protected function setUp(): void {
		$this->parser = new StackTraceParser();
	}

	public function testTheDeployDirectorySharedByTheFramesIsStripped() {
		$trace = $this->parser->parse(serialize([
			new Codeframe('/var/www/releases/28/src/Controller/MailsController.php', 161, [], ''),
			new Codeframe('/var/www/releases/28/vendor/symfony/HttpKernel.php', 183, [], ''),
		]));

		$this->assertSame([
			'/src/Controller/MailsController.php',
			'/vendor/symfony/HttpKernel.php',
		], $this->files($trace));
	}

	/**
	 * Otherwise sibling files such as `/app/src/Runner.php` and `/app/src/RunnerFactory.php` share
	 * the prefix `/app/src/Runner` and the shortened paths lose part of their name.
	 */
	public function testThePrefixIsCutOnADirectoryBoundary() {
		$trace = $this->parser->parse(serialize([
			new Codeframe('/app/src/Runner.php', 12, [], ''),
			new Codeframe('/app/src/RunnerFactory.php', 30, [], ''),
		]));

		$this->assertSame(['/Runner.php', '/RunnerFactory.php'], $this->files($trace));
	}

	/**
	 * A reporter may hand over labels such as "Caused by: ..." instead of a path. A single one of
	 * those must not collapse the common prefix and leave every frame with the full deploy path.
	 */
	public function testFramesThatAreNotAPathDoNotDisableShortening() {
		$trace = $this->parser->parse(serialize([
			new Codeframe('Caused by: RuntimeException', 0, [], ''),
			new Codeframe('/app/src/Controller/MailsController.php', 161, [], ''),
			new Codeframe('/app/vendor/symfony/HttpKernel.php', 183, [], ''),
		]));

		$this->assertSame([
			'Caused by: RuntimeException',
			'/src/Controller/MailsController.php',
			'/vendor/symfony/HttpKernel.php',
		], $this->files($trace));
	}

	public function testWindowsPathsAreShortenedToo() {
		$trace = $this->parser->parse(serialize([
			new Codeframe('C:\\projects\\app\\src\\Runner.php', 12, [], ''),
			new Codeframe('C:\\projects\\app\\vendor\\symfony\\Kernel.php', 30, [], ''),
		]));

		$this->assertSame([
			'\\src\\Runner.php',
			'\\vendor\\symfony\\Kernel.php',
		], $this->files($trace));
	}

	/**
	 * A bare "/" carries no information, there is nothing worth cutting.
	 */
	public function testFramesSharingOnlyTheRootAreLeftUntouched() {
		$trace = $this->parser->parse(serialize([
			new Codeframe('/src/Runner.php', 12, [], ''),
			new Codeframe('/vendor/symfony/Kernel.php', 30, [], ''),
		]));

		$this->assertSame(['/src/Runner.php', '/vendor/symfony/Kernel.php'], $this->files($trace));
	}

	/**
	 * The frames do not have to be a zero indexed list - a reporter may hand over whatever keys its
	 * own filtering left behind.
	 */
	public function testTheOriginalFrameKeysArePreserved() {
		$trace = $this->parser->parse(serialize([
			3 => new Codeframe('/app/src/Runner.php', 12, [], ''),
			7 => new Codeframe('/app/vendor/symfony/Kernel.php', 30, [], ''),
		]));

		$this->assertSame([3, 7], array_keys($trace));
	}

	/**
	 * With a single frame the whole directory of that frame is the common prefix, so nothing but
	 * the file name is left. Degenerate, but the trace stays readable and the full path is of no
	 * use to the reader anyway - it points inside the deploy directory of the reporting server.
	 */
	public function testASingleFrameIsReducedToItsFileName() {
		$trace = $this->parser->parse(serialize([
			new Codeframe('/app/src/Runner.php', 12, [], ''),
		]));

		$this->assertSame(['/Runner.php'], $this->files($trace));
	}

	public function testGarbageIsRejected() {
		$this->expectException(MalformedStackTraceException::class);

		$this->parser->parse('not serialized');
	}

	public function testSerializedDataThatIsNotAListOfFramesIsRejected() {
		$this->expectException(MalformedStackTraceException::class);

		$this->parser->parse(serialize(['just a string']));
	}

	/**
	 * Records are reported by client applications over a public API, so the serialized payload is
	 * untrusted input. Restoring arbitrary classes out of it would hand an attacker the object
	 * instantiation half of a PHP object injection chain.
	 */
	public function testOtherClassesAreNotRestoredFromTheSerializedPayload() {
		$this->expectException(MalformedStackTraceException::class);

		$this->parser->parse(serialize([new \ArrayObject(['payload'])]));
	}

	/**
	 * An empty list is a valid - if useless - trace, not a broken payload. Reporting it as
	 * malformed would tell the reader the trace could not be read, which is not what happened.
	 */
	public function testAnEmptyTraceStaysEmpty() {
		$this->assertSame([], $this->parser->parse(serialize([])));
	}

	/**
	 * @param Codeframe[] $trace
	 *
	 * @return string[]
	 */
	private function files(array $trace): array {
		return array_values(array_map(fn(Codeframe $frame) => $frame->getFile(), $trace));
	}
}
