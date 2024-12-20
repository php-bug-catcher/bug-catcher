<?php

namespace BugCatcher\Command;

use BugCatcher\Entity\RecordPing;
use BugCatcher\Repository\ProjectRepository;
use BugCatcher\Repository\RecordPingRepository;
use BugCatcher\Service\PingCollector\PingCollectorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Throwable;

#[AsCommand(
	name: 'app:ping-collector',
)]
final class PingCollectorCommand extends Command implements ServiceSubscriberInterface
{
	public function __construct(
		private readonly array                $collectors,
		private readonly ProjectRepository    $projectRepo,
		private readonly RecordPingRepository $pingRecordRepo
	) {
		parent::__construct();
	}


	protected function execute(InputInterface $input, OutputInterface $output): int {
		$projects = $this->projectRepo->findBy(["enabled" => true]);
		foreach ($projects as $project) {
			/** @var PingCollectorInterface $collector */
			$collector = $this->collectors[$project->getPingCollector()]??null;
			if (!$collector) {
				continue;
			}
			try {
				$status = $collector->ping($project);
			} catch (Throwable $e) {
				$status = Response::HTTP_INTERNAL_SERVER_ERROR;
			}
			$this->pingRecordRepo->save(new RecordPing($project, $status), true);
		}

		return Command::SUCCESS;
	}

	public static function getSubscribedServices(): array {
		return [];
	}
}
