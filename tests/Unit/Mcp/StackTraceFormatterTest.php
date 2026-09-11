<?php

namespace BugCatcher\Tests\Unit\Mcp;

use BugCatcher\Mcp\StackTraceFormatter;
use BugCatcher\Service\StackTrace\StackTraceParser;
use Kregel\ExceptionProbe\Codeframe;
use PHPUnit\Framework\TestCase;

class StackTraceFormatterTest extends TestCase {

	private StackTraceFormatter $formatter;

	protected function setUp(): void {
		$this->formatter = new StackTraceFormatter(new StackTraceParser());
	}

	public function testAFrameIsRenderedAsFileLineFollowedByItsCall() {
		$text = $this->formatter->format(serialize([
			new Codeframe('/app/src/Cpe/GponRadius.php', 58, [], 'RuntimeException: boom'),
			new Codeframe('/app/src/Runner.php', 12, [], 'GponRadius->proceed(Object)'),
		]));

		$this->assertSame(
			"#0 /Cpe/GponRadius.php:58  RuntimeException: boom\n"
			. "#1 /Runner.php:12  GponRadius->proceed(Object)",
			$text
		);
	}

	/**
	 * The surrounding code is the whole point of reading a trace through the MCP server - it saves
	 * the reader a round trip to the file, which it may not even have at the reported revision.
	 */
	public function testTheCodeOfAFrameIsIndentedUnderItWithTheFailingLineMarked() {
		$text = $this->formatter->format(serialize([
			new Codeframe('/app/src/Cpe/GponRadius.php', 58, [
				57 => "\t\$this->queries = [];\n",
				58 => "\t\$this->proceed(\$ont);\n",
				59 => "\t\$this->save();\n",
			], 'RuntimeException: boom'),
		]));

		$this->assertSame(
			"#0 /GponRadius.php:58  RuntimeException: boom\n"
			. "      57 | \t\$this->queries = [];\n"
			. "  >   58 | \t\$this->proceed(\$ont);\n"
			. "      59 | \t\$this->save();",
			$text
		);
	}

	/**
	 * A reporter may hand over labels such as "Caused by: ..." instead of a path. Those carry no
	 * line, and a trailing ":0" would read as if the frame pointed at the top of a file.
	 */
	public function testAFrameWithoutALineIsRenderedWithoutOne() {
		$text = $this->formatter->format(serialize([
			new Codeframe('Caused by: RuntimeException', 0, [], ''),
			new Codeframe('/app/src/Runner.php', 12, [], 'run()'),
		]));

		$this->assertSame(
			"#0 Caused by: RuntimeException\n"
			. "#1 /Runner.php:12  run()",
			$text
		);
	}

	public function testFramesAreNumberedByPositionNotByTheKeysTheReporterLeftBehind() {
		$text = $this->formatter->format(serialize([
			3 => new Codeframe('/app/src/Runner.php', 12, [], ''),
			7 => new Codeframe('/app/vendor/symfony/Kernel.php', 30, [], ''),
		]));

		$this->assertSame(
			"#0 /src/Runner.php:12\n"
			. "#1 /vendor/symfony/Kernel.php:30",
			$text
		);
	}

	public function testARecordWithoutATraceHasNothingToFormat() {
		$this->assertNull($this->formatter->format(null));
		$this->assertNull($this->formatter->format(''));
	}

	/**
	 * Saying "no stack trace" would be a lie - there is one, it just cannot be read. The reader has
	 * to be able to tell the two apart, otherwise it goes looking for a cause that is not there.
	 */
	public function testAnUnreadableTraceIsReportedAsSuch() {
		$this->assertSame(
			'<stack trace is stored but could not be read>',
			$this->formatter->format('not serialized')
		);
	}

	public function testAnEmptyTraceHasNothingToFormat() {
		$this->assertNull($this->formatter->format(serialize([])));
	}
}
