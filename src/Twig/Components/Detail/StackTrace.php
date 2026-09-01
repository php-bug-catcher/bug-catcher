<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 1. 6. 2024
 * Time: 7:11
 */
namespace BugCatcher\Twig\Components\Detail;

use Exception;
use Kregel\ExceptionProbe\Codeframe;
use BugCatcher\Entity\Record;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(template: '@BugCatcher/components/Detail/StackTrace.html.twig')]
final class StackTrace
{
	public Record $record;
	/**
	 * @var  Codeframe[]
	 */
	public ?array $trace = null;
	public int $opened = 0;

	public function mount(Record $record): void {
		$this->record = $record;
		if ($this->record->getStacktrace()) {
			try {
				$this->trace = unserialize($this->record->getStacktrace());
				$this->fixPaths();
				$this->opened = 0;
				foreach ($this->trace as $pos => $frame) {
					if (!str_contains($frame->file, '/vendor/')) {
						$this->opened = $pos;
						break;
					}
				}
			} catch (Exception $e) {
				$this->trace = [
					new Codeframe("Unable to unserialize stacktrace", 0, [], ""),
				];
			}
		}
	}

	/**
	 * The project relative path with its line number, in the `file:line` form PhpStorm and most
	 * other editors accept when pasted into their "open file" prompt.
	 *
	 * Deliberately not the absolute path the record was reported with: that one points inside the
	 * deploy directory of the production server and does not exist on the developer's machine.
	 */
	public function copyPath(int|string $pos): ?string {
		if (!isset($this->trace[$pos]) || $this->trace[$pos]->line < 1) {
			return null;
		}

		return ltrim($this->trace[$pos]->file, '/\\') . ':' . $this->trace[$pos]->line;
	}

	private function fixPaths(): void {
		$prefix = $this->findSimilarPrefix();
		// a bare "/" carries no information, there is nothing worth cutting
		if (strlen($prefix) < 2) {
			return;
		}
		foreach ($this->trace as $frame) {
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
	 */
	private function findSimilarPrefix(): string {
		$prefix = null;
		foreach ($this->trace as $frame) {
			if (!$this->isPath($frame->file)) {
				continue;
			}
			if ($prefix === null) {
				$prefix = $frame->file;
				continue;
			}
			$common = min(strlen($prefix), strlen($frame->file));
			for ($i = 0; $i < $common; $i++) {
				if ($prefix[$i] !== $frame->file[$i]) {
					break;
				}
			}
			$prefix = substr($prefix, 0, $i);
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

	private function isPath(string $file): bool {
		return str_starts_with($file, '/') || preg_match('#^[a-zA-Z]:[\\\\/]#', $file) === 1;
	}

}