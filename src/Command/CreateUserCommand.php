<?php

namespace BugCatcher\Command;

use Exception;
use BugCatcher\Entity\Role;
use BugCatcher\Repository\UserRepository;
use BugCatcher\Service\Transaction;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Tito10047\DoctrineTransaction\TransactionManagerInterface;

#[AsCommand(
	name: 'app:create-user',
	description: 'Initialize the app with superadmin',
)]
final class CreateUserCommand extends Command
{
	public function __construct(
		private readonly UserRepository              $userRepo,
        private readonly TransactionManagerInterface $tm,
		private readonly UserPasswordHasherInterface $passwordHasher
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->addArgument('username', InputArgument::REQUIRED, 'Username for superadmin')
			->addArgument('password', InputArgument::REQUIRED, 'Password for created user');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$io       = new SymfonyStyle($input, $output);
		$username = $input->getArgument('username');
		$password = $input->getArgument('password');

        $transaction = $this->tm->beginTransaction();
		try {
			$user = $this->userRepo->createEmpty($username, true);
			$user
				->setEnabled(true)
                ->setRoles(['ROLE_ADMIN']);
            $this->userRepo->upgradePassword($user,
                $this->passwordHasher->hashPassword($user, $password)
            );

			$this->userRepo->save($user, true);


			$io->success("User {$user->getEmail()} created");
            $transaction->commit();
		} catch (Exception $e) {
            $transaction->rollback();
			throw $e;
		}

		return Command::SUCCESS;
	}
}
