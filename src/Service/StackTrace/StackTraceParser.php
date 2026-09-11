<?php

namespace BugCatcher\Service\StackTrace;

use Kregel\ExceptionProbe\Codeframe;

/**
 * Restores the stored stack trace of a record into code frames with readable paths.
 *
 * `RecordLogTrace::stackTrace` holds a `serialize()`d list of `Codeframe` objects, produced on the
 * client side from the absolute paths of the reporting server. Those paths are shortened here so
 * they read as project relative ones - see `findSimilarPrefix()`.
 */
final class StackTraceParser
{
	/**
	 * @return Codeframe[] keyed the way the reporter left them
	 *
	 * @throws MalformedStackTraceException
	 */
	public function parse(string $serialized): array {
		$trace = @unserialize($serialized, ['allowed_classes' => [Codeframe::class]]);

		if (!is_array($trace)) {
			throw new MalformedStackTraceException('payload is not a serialized array');
		}
		foreach ($trace as $frame) {
			// anything but a Codeframe also covers __PHP_Incomplete_Class, which is what the
			// allowed_classes restriction above turns every other class into
			if (!$frame instanceof Codeframe) {
				throw new MalformedStackTraceException('payload holds something other than code frames');
			}
		}

		$this->shortenPaths($trace);

		return $trace;
	}

	/**
	 * @param Codeframe[] $trace
	 */
	private function shortenPaths(array $trace): void {
		$prefix = $this->findSimilarPrefix($trace);
		// a bare "/" carries no information, there is nothing worth cutting
		if (strlen($prefix) < 2) {
			return;
		}
		foreach ($trace as $frame) {
			if (str_starts_with($frame->file, $prefix)) {
				// keep the trailing separator of the prefix, so the path stays rooted
				$frame->file = substr($frame->file, strlen($prefix) - 1);
			}
		}
	}

	/**
	 * The deploy directory shared by the frames, cut on a directory boundary.
	 *
	 * Frames whose file is not a path are skipped: a reporter may hand over labels such as
	 * "Caused by: ..." or "[internal function]", and a single one of those would collapse the
	 * common prefix to nothing and leave every frame with the full deploy path of the server.
	 *
	 * @param Codeframe[] $trace
	 */
	private function findSimilarPrefix(array $trace): string {
		$prefix = null;
		foreach ($trace as $frame) {
			if (!$this->isPath($frame->file)) {
				continue;
			}
			if ($prefix === null) {
				$prefix = $frame->file;
				continue;
			}
			$prefix = substr($prefix, 0, $this->commonLength($prefix, $frame->file));
		}
		if ($prefix === null) {
			return '';
		}

		// cut on a directory boundary, otherwise `/app/src/Foo.php` and `/app/src/Fbar.php` share
		// the prefix `/app/src/F` and the shortened paths become unusable
		$cut = max(
			($pos = strrpos($prefix, '/')) === false ? -1 : $pos,
			($pos = strrpos($prefix, '\\')) === false ? -1 : $pos,
		);

		return $cut < 0 ? '' : substr($prefix, 0, $cut + 1);
	}

	private function commonLength(string $a, string $b): int {
		$max = min(strlen($a), strlen($b));
		for ($i = 0; $i < $max; $i++) {
			if ($a[$i] !== $b[$i]) {
				return $i;
			}
		}

		return $max;
	}

	private function isPath(string $file): bool {
		return str_starts_with($file, '/') || preg_match('#^[a-zA-Z]:[\\\\/]#', $file) === 1;
	}
}
