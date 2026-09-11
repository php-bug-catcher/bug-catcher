<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 1. 6. 2024
 * Time: 7:11
 */
namespace BugCatcher\Twig\Components\Detail;

use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordLogTrace;
use BugCatcher\Service\StackTrace\MalformedStackTraceException;
use BugCatcher\Service\StackTrace\StackTraceParser;
use Kregel\ExceptionProbe\Codeframe;
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

	public function __construct(private readonly StackTraceParser $parser) {}

	public function mount(Record $record): void {
		$this->record = $record;
		if (!$record instanceof RecordLogTrace || !$record->getStackTrace()) {
			return;
		}

		try {
			$this->trace = $this->parser->parse($record->getStackTrace());
		} catch (MalformedStackTraceException) {
			// the reader gets a trace shaped placeholder instead of an empty panel - the reason is
			// of no use to them, the payload was written by the reporting client
			$this->trace = [new Codeframe("Unable to unserialize stacktrace", 0, [], "")];

			return;
		}

		$this->opened = $this->firstOwnFrame();
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

	/**
	 * The frame the reader is after: the topmost one that is not third party code.
	 */
	private function firstOwnFrame(): int {
		foreach ($this->trace as $pos => $frame) {
			if (!str_contains($frame->file, '/vendor/')) {
				return $pos;
			}
		}

		return 0;
	}
}
