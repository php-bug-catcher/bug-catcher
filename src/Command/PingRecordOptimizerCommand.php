<?php

namespace BugCatcher\Command;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordPing;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
	name: 'app:record-optimizer',
	description: 'Add a short description for your command',
)]
class PingRecordOptimizerCommand extends Command {
	public function __construct(
		private readonly EntityManagerInterface $em,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->addOption('past', "p", InputOption::VALUE_REQUIRED, 'Days in the past to start from')
			->addOption('precision', "i", InputOption::VALUE_REQUIRED, 'Precision in minutes to group logs to');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$io = new SymfonyStyle($input, $output);

		$past      = (int)$input->getOption('past');
		$precision = (int)$input->getOption('precision');

		$sql       = <<<SQL
select
    project_id, status_code,
    count(*) as cnt ,
    concat(DATE_FORMAT(`date`,'%Y-%m-%d %H:'),TIME_FORMAT(SEC_TO_TIME(((DATE_FORMAT(`date`,'%i') div {$precision})*{$precision})*60),'%i'),':00') as period
from record_ping
join record on record_ping.id = record.id
where date < DATE_SUB(NOW(), INTERVAL {$past}  DAY)
group by `period`, status_code, project_id
SQL;
		$deleteSql = <<<SQL
delete record_ping, record from record_ping
join record on record_ping.id = record.id
where date < DATE_SUB(NOW(), INTERVAL {$past} DAY)
SQL;

		$this->em->beginTransaction();
		$total = 0;
		try {
			$rows = $this->em->getConnection()->executeQuery($sql)->fetchAllAssociative();
			$this->em->getConnection()->executeQuery($deleteSql);
			foreach ($rows as $pos => $row) {
				$ping = new RecordPing(
					$this->em->getReference(Project::class, Uuid::fromString($row["project_id"])),
					$row["status_code"],
					new DateTimeImmutable($row["period"]),
				);
				$this->em->persist($ping);
				$total += $row["cnt"];
			}
			$this->em->flush();
			$this->em->commit();
		} catch (Exception $e) {
			$this->em->getConnection()->rollBack();
			throw $e;
		}


		$io->success('Optimized ' . $total . ' records.');

		return Command::SUCCESS;
	}
}
