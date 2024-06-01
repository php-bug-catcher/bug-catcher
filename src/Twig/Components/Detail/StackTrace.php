<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 1. 6. 2024
 * Time: 7:11
 */
namespace PhpSentinel\BugCatcher\Twig\Components\Detail;

use Kregel\ExceptionProbe\Codeframe;
use PhpSentinel\BugCatcher\Entity\Record;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(template: '@BugCatcher/components/Detail/StackTrace.html.twig')]
class StackTrace {
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
			} catch (\Exception $e) {
				$this->trace = [
					new Codeframe("Unable to unserialize stacktrace", 0, [], ""),
				];
			}
		}
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

		return $prefix;
	}

}