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
	public function copyPath(int $pos): ?string {
		if (!isset($this->trace[$pos]) || $this->trace[$pos]->line < 1) {
			return null;
		}

		return ltrim($this->trace[$pos]->file, '/') . ':' . $this->trace[$pos]->line;
	}

	private function fixPaths(): void {
		$prefix = $this->findSimilarPrefix();
		if ($prefix == '') {
			return;
		}
		foreach ($this->trace as $frame) {
			$frame->file = str_replace($prefix, '/', $frame->file);
		}
	}

	private function findSimilarPrefix(): string {
		if (count($this->trace) == 0) {
			return '';
		}
		$prefix = $this->trace[0]->file;
		foreach ($this->trace as $frame) {
			$string       = $frame->file;
			$dlzkaPrefixu = strlen($prefix);
			$dlzkaStringu = strlen($string);

			if ($dlzkaStringu < $dlzkaPrefixu) {
				$prefix = substr($prefix, 0, $dlzkaStringu);
			}

			for ($i = 0; $i < $dlzkaPrefixu; $i++) {
				if ($prefix[$i] != $string[$i]) {
					$prefix = substr($prefix, 0, $i);
					break;
				}
			}
		}

		// cut on a directory boundary, otherwise `/app/src/Foo.php` and `/app/src/Fbar.php` share
		// the prefix `/app/src/F` and the shortened paths become unusable
		$lastSlash = strrpos($prefix, '/');

		return $lastSlash === false ? '' : substr($prefix, 0, $lastSlash + 1);
	}

}