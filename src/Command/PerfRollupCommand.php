<?php

declare(strict_types=1);

namespace BugCatcher\Command;

use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Rollup\RollupService;
use BugCatcher\Service\Perf\Rollup\RollupWindowResolver;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Hours out of minutes, days out of hours.
 *
 * Cron runs it at `:17` for hours and at `04:23` for days, each over the bucket that has just
 * finished. Running it again over a window it has already done is a no-op, so a missed night is
 * caught up with `--from`.
 */
#[AsCommand(
	name: 'app:perf:rollup',
	description: 'Compute hour and day performance buckets out of the finer ones',
)]
final class PerfRollupCommand extends Command
{
	public function __construct(
		private readonly RollupWindowResolver $windows,
		private readonly RollupService $rollup,
		private readonly bool $enabled,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addOption(
				'granularity',
				'g',
				InputOption::VALUE_REQUIRED,
				'What to compute: hour or day',
				PerfGranularity::Hour->value,
			)
			->addOption('from', null, InputOption::VALUE_REQUIRED, 'First bucket to compute, any date PHP reads')
			->addOption('to', null, InputOption::VALUE_REQUIRED, 'Last bucket to compute, included')
			->setHelp(<<<'HELP'
				Without <info>--from</info> and <info>--to</info> this computes the last completed
				bucket - the one still filling up is left for the next run. A bound names a bucket
				rather than a boundary, so <info>--granularity=day --from=2026-03-01
				--to=2026-03-05</info> is five days.
				HELP);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		if (!$this->enabled) {
			$io->error('Performance monitoring is off (bug_catcher.perf.enabled); nothing was rolled up.');

			return self::FAILURE;
		}

		$name        = (string)$input->getOption('granularity');
		$granularity = PerfGranularity::tryFrom($name);

		if ($granularity === null) {
			$io->error(sprintf(
				'%s is not a granularity. Known: %s.',
				$name,
				implode(', ', array_column(PerfGranularity::cases(), 'value')),
			));

			return self::INVALID;
		}

		try {
			$window = $this->windows->resolve($granularity, $input->getOption('from'), $input->getOption('to'));
		} catch (InvalidArgumentException $e) {
			$io->error($e->getMessage());

			return self::INVALID;
		}

		$result = $this->rollup->rollUp($window);

		$io->writeln(sprintf(
			'%s %s to %s: %s.',
			$granularity->value,
			$window->from->format('Y-m-d H:i'),
			$window->to->format('Y-m-d H:i'),
			$result->summary(),
		));

		return self::SUCCESS;
	}
}
