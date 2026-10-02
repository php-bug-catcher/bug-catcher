<?php

declare(strict_types=1);

namespace BugCatcher\Command;

use BugCatcher\Service\Perf\Detection\DetectionRunner;
use BugCatcher\Service\Perf\Detection\DetectionWindowResolver;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Looks at the minutes that have just finished and records what regressed.
 *
 * Cron runs it every five minutes. `--window` has to match that interval: a window narrower than
 * the cron period leaves minutes nobody ever examines, and a wider one examines the same minutes
 * twice and records the same regression twice.
 */
#[AsCommand(
	name: 'app:perf:detect',
	description: 'Find routes whose performance regressed and record them',
)]
final class PerfDetectCommand extends Command
{
	private const int DEFAULT_WINDOW_MINUTES = 5;

	public function __construct(
		private readonly DetectionWindowResolver $windows,
		private readonly DetectionRunner $detection,
		private readonly bool $enabled,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addOption(
			'window',
			'w',
			InputOption::VALUE_REQUIRED,
			'How many of the last completed minutes to look at; match it to the cron interval',
			(string)self::DEFAULT_WINDOW_MINUTES,
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		if (!$this->enabled) {
			$io->error('Performance monitoring is off (bug_catcher.perf.enabled); nothing was examined.');

			return self::FAILURE;
		}

		try {
			$window = $this->windows->lastCompleted((int)$input->getOption('window'));
		} catch (InvalidArgumentException $e) {
			$io->error($e->getMessage());

			return self::INVALID;
		}

		$result = $this->detection->run($window);

		$io->writeln(sprintf(
			'%s to %s (%d minutes): %s.',
			$window->from->format('Y-m-d H:i'),
			$window->to->format('H:i'),
			($window->to->getTimestamp() - $window->from->getTimestamp()) / 60,
			$result->summary(),
		));

		return self::SUCCESS;
	}
}
