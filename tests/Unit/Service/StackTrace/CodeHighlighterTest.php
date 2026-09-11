<?php

namespace BugCatcher\Tests\Unit\Service\StackTrace;

use BugCatcher\Service\StackTrace\CodeHighlighter;
use PHPUnit\Framework\TestCase;

final class CodeHighlighterTest extends TestCase
{
	private CodeHighlighter $highlighter;

	protected function setUp(): void {
		$this->highlighter = new CodeHighlighter();
	}

	public function testKeepsTheLineNumbersItWasGiven(): void {
		$lines = [
			56 => '    if ($response !== 1) {',
			57 => '        throw new ApiException($response);',
			58 => '    }',
		];

		$this->assertSame([56, 57, 58], array_keys($this->highlighter->highlight($lines)));
	}

	public function testHighlightsPhpWithoutAnOpeningTag(): void {
		// a code frame is a slice out of the middle of a file, it never starts with <?php
		$highlighted = $this->highlighter->highlight([1 => 'throw new ApiException($response);']);

		$this->assertStringContainsString('hl-keyword', $highlighted[1]);
	}

	public function testEscapesMarkupInTheSource(): void {
		$highlighted = $this->highlighter->highlight([1 => '$x = "<script>alert(1)</script>";']);

		$this->assertStringNotContainsString('<script>', $highlighted[1]);
		$this->assertStringContainsString('&lt;script&gt;', $highlighted[1]);
	}

	/**
	 * The frame is tokenised as one block so that docblocks and multi-line strings come out right,
	 * which leaves spans straddling newlines. Every line still has to stand on its own, because
	 * the template wraps each one in its own <code> element.
	 */
	public function testEachLineIsBalancedEvenWhenAConstructSpansSeveralLines(): void {
		$lines = [
			10 => '/**',
			11 => ' * Does the thing.',
			12 => ' */',
			13 => 'function thing(int $a): string {',
		];

		$highlighted = $this->highlighter->highlight($lines);

		$this->assertCount(4, $highlighted);
		foreach ($highlighted as $number => $line) {
			$this->assertSame(
				substr_count($line, '<span'),
				substr_count($line, '</span>'),
				"line {$number} has unbalanced spans: {$line}"
			);
		}
	}

	public function testTheSpanningConstructStaysHighlightedOnEveryLineItCovers(): void {
		$highlighted = $this->highlighter->highlight([
			10 => '/**',
			11 => ' * Does the thing.',
			12 => ' */',
		]);

		foreach ($highlighted as $number => $line) {
			$this->assertStringContainsString('hl-comment', $line, "line {$number} lost its comment styling");
		}
	}

	public function testStripsNothingFromTheSource(): void {
		$lines = [
			1 => '$a = 1;',
			2 => '',
			3 => '$b = $a + 2;',
		];

		$highlighted = $this->highlighter->highlight($lines);

		$this->assertCount(3, $highlighted);
		foreach ($lines as $number => $source) {
			$this->assertSame($source, html_entity_decode(strip_tags($highlighted[$number])));
		}
	}

	/**
	 * Code frames arrive from the reporting client with the file's line terminators still on them.
	 * Joining those with another newline used to double every break, which knocked the split out of
	 * step with the line numbers and dropped the whole frame back to unhighlighted text.
	 */
	public function testHandlesSourceLinesThatKeepTheirLineTerminator(): void {
		$highlighted = $this->highlighter->highlight([
			28 => "    if (\$response !== 1) {\n",
			29 => "        throw new ApiException();\r\n",
			30 => "    }\n",
		]);

		$this->assertSame([28, 29, 30], array_keys($highlighted));
		$this->assertStringContainsString('hl-keyword', $highlighted[28]);
		$this->assertStringContainsString('hl-keyword', $highlighted[29]);
		$this->assertStringNotContainsString("\n", $highlighted[29]);
	}

	public function testEmptyFrame(): void {
		$this->assertSame([], $this->highlighter->highlight([]));
	}
}
