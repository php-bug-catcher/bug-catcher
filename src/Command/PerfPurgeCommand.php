<?php

declare(strict_types=1);

namespace BugCatcher\Command;

use BugCatcher\Service\Perf\Retention\PurgeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Drops the performance buckets that are past their retention.
 *
 * Runs nightly after the day roll-up, because what it deletes is what the roll-up has already
 * folded into coarser rows.
 */
#[AsCommand(
	name: 'app:perf:purge',
	description: 'Delete performance buckets beyond their retention',
)]
final class PerfPurgeCommand extends Command
{
	public function __construct(
		private readonly PurgeService $purge,
		private readonly bool $enabled,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count what would go, delete nothing');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		if (!$this->enabled) {
			$io->error('Performance monitoring is off (bug_catcher.perf.enabled); nothing was purged.');

			return self::FAILURE;
		}

		$io->writeln($this->purge->purge((bool)$input->getOption('dry-run'))->summary() . '.');

		return self::SUCCESS;
	}
}
