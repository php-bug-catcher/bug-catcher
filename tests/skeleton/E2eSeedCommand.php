<?php

declare(strict_types=1);

namespace App\Command;

use BugCatcher\Entity\Project;
use BugCatcher\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Copied into the E2E workdir as src/Command/E2eSeedCommand.php by tests/skeleton/e2e.sh.
 *
 * The dashboard shows the projects the signed-in user is on, and the ingest API resolves a project
 * by code, so an installation with neither has nothing to assert about. Creating them from an
 * application command rather than with SQL is the point: it is also the check that `App\` autowiring
 * reaches the bundle's entities and repositories in a project built from the skeleton.
 */
#[AsCommand(name: 'e2e:seed', description: 'Creates the project the E2E run sends records to.')]
final class E2eSeedCommand extends Command
{
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly UserRepository $users,
	) {
		parent::__construct();
	}

	protected function configure(): void
	{
		$this
			->addArgument('email', InputArgument::REQUIRED, 'the user the project is attached to')
			->addArgument('code', InputArgument::REQUIRED, 'project code the API will be called with')
			->addArgument('url', InputArgument::REQUIRED, 'what app:ping-collector pings');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$email = $input->getArgument('email');
		$user  = $this->users->findOneBy(['email' => $email]);

		if (null === $user) {
			throw new RuntimeException("no user {$email}; app:create-user has to run first");
		}

		$project = new Project();
		$project
			->setCode($input->getArgument('code'))
			->setName('E2E')
			->setEnabled(true)
			// the latency row on the dashboard and the panels on /performance, so the perf half of
			// the installation is exercised too
			->setPerfEnabled(true)
			->setUrl($input->getArgument('url'))
			->setPingCollector('http')
			->addUser($user);

		$this->em->persist($project);
		$this->em->flush();

		$output->writeln((string) $project->getId());

		return Command::SUCCESS;
	}
}
