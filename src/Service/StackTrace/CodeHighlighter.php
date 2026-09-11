<?php

namespace BugCatcher\Service\StackTrace;

use Tempest\Highlight\Highlighter;
use Tempest\Highlight\Themes\CssTheme;

/**
 * Syntax highlights a stack trace code frame, returning one entry per source line.
 *
 * The frame is highlighted as a whole rather than line by line: a docblock or a multi-line string
 * only tokenises correctly with its surrounding context. That means the highlighter emits spans
 * which straddle newlines, while the template needs one <code> element per line to drive the CSS
 * line-number counter. So the result is split back into lines, closing whatever is open at the end
 * of a line and reopening it on the next - each line is therefore valid HTML on its own.
 */
final class CodeHighlighter
{
	private readonly Highlighter $highlighter;

	public function __construct(?Highlighter $highlighter = null) {
		// CssTheme emits class names (hl-keyword, hl-comment, ...) instead of inline styles, which
		// lets app.css map them onto the design tokens and follow the light/dark theme.
		$this->highlighter = $highlighter ?? new Highlighter(new CssTheme());
	}

	/**
	 * @param array<int, string> $lines source lines keyed by their line number in the file
	 *
	 * @return array<int, string> the same keys, values are highlighted and HTML-escaped markup
	 */
	public function highlight(array $lines, string $language = 'php'): array {
		if ($lines === []) {
			return [];
		}

		// The frames arrive from the reporting client with the file's own line terminators still
		// attached. Joining those with another newline would double every line break and throw the
		// split back out of step with the line numbers.
		$lines = array_map(static fn(string $line) => rtrim($line, "\r\n"), $lines);

		$highlighted = $this->splitLines($this->highlighter->parse(implode("\n", $lines), $language));

		// Losing or gaining a line would silently misalign every line number against its source,
		// which is worse than no colour at all - fall back to plain escaped text.
		if (count($highlighted) !== count($lines)) {
			return array_map(htmlspecialchars(...), $lines);
		}

		return array_combine(array_keys($lines), $highlighted);
	}

	/**
	 * @return list<string>
	 */
	private function splitLines(string $html): array {
		$lines   = [];
		$current = '';
		$open    = [];

		// One match per tag or per run of text between tags, so a tag is never cut in half.
		preg_match_all('#<span[^>]*>|</span>|[^<]+#', $html, $matches);

		foreach ($matches[0] as $token) {
			if ($token === '</span>') {
				array_pop($open);
				$current .= $token;

				continue;
			}

			if (str_starts_with($token, '<')) {
				$open[]  = $token;
				$current .= $token;

				continue;
			}

			foreach (explode("\n", $token) as $index => $text) {
				if ($index > 0) {
					$lines[] = $current . str_repeat('</span>', count($open));
					$current = implode('', $open);
				}
				$current .= $text;
			}
		}

		$lines[] = $current;

		return $lines;
	}
}
