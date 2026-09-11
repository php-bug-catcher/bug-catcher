<?php

namespace BugCatcher\Mcp;

use BugCatcher\Service\StackTrace\MalformedStackTraceException;
use BugCatcher\Service\StackTrace\StackTraceParser;
use Kregel\ExceptionProbe\Codeframe;

/**
 * Renders the stored stack trace of a record as plain text, for the MCP client to read.
 *
 * The dashboard renders the same frames as a collapsible accordion; an MCP client gets one text
 * block instead, in the shape a developer expects from a trace:
 *
 *     #0 /src/Cpe/GponRadius.php:58  RuntimeException: boom
 *           57 | $this->queries = [];
 *       >   58 | $this->proceed($ont);
 *           59 | $this->save();
 *     #1 /src/Runner.php:12  GponRadius->proceed(Object)
 */
final class StackTraceFormatter
{
	/**
	 * Width of the gutter holding the "reported line" marker, see `renderCode()`.
	 */
	private const MARKER_WIDTH = 3;

	/**
	 * Width of the line number column. Five digits cover any real file; longer numbers just push
	 * the column, they are never cut.
	 */
	private const LINE_NUMBER_WIDTH = 5;

	public const UNREADABLE = '<stack trace is stored but could not be read>';

	public function __construct(private readonly StackTraceParser $parser) {}

	/**
	 * @param string|null $serialized the raw `RecordLogTrace::stackTrace` column
	 *
	 * @return string|null null when the record carries no trace at all
	 */
	public function format(?string $serialized): ?string {
		if ($serialized === null || $serialized === '') {
			return null;
		}

		try {
			$trace = $this->parser->parse($serialized);
		} catch (MalformedStackTraceException) {
			// not null: "no stack trace" and "a stack trace nobody can read" are different facts,
			// and a reader told the former goes looking for a cause that is not there
			return self::UNREADABLE;
		}

		if ($trace === []) {
			return null;
		}

		$lines = [];
		// numbered by position: the keys are an artifact of whatever filtering the reporting
		// client did, they carry no meaning for the reader
		foreach (array_values($trace) as $position => $frame) {
			$lines[] = $this->renderHeader($position, $frame);
			foreach ($frame->code as $number => $code) {
				$lines[] = $this->renderCode((int)$number, (string)$code, $frame->line);
			}
		}

		return implode("\n", $lines);
	}

	private function renderHeader(int $position, Codeframe $frame): string {
		// a frame may hold a label such as "Caused by: ..." instead of a path, and a trailing ":0"
		// would read as if it pointed at the top of a file
		$location = $frame->line > 0 ? $frame->file . ':' . $frame->line : $frame->file;
		$call     = $frame->frame === '' ? '' : '  ' . $frame->frame;

		return '#' . $position . ' ' . $location . $call;
	}

	private function renderCode(int $number, string $code, int $reportedLine): string {
		$marker = str_pad($number === $reportedLine ? '  >' : '', self::MARKER_WIDTH);

		return $marker
			. str_pad((string)$number, self::LINE_NUMBER_WIDTH, ' ', STR_PAD_LEFT)
			. ' | '
			// the frames carry the lines straight off `fgets()`, newline included
			. rtrim($code, "\r\n");
	}
}
