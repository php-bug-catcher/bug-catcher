<?php

namespace App\Command;

use App\Entity\Role;
use App\Repository\UserRepository;
use App\Service\Transaction;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
	name: 'app:init',
	description: 'Initialize the app with superadmin',
)]
class InitCommand extends Command {
	public function __construct(
		private readonly UserRepository              $userRepo,
		private readonly Transaction                 $transaction,
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

		try {
			$this->transaction->begin();
			$user = $this->userRepo->createEmpty($username, true);
			$user
				->setEnabled(true)
				->setEnumRoles([Role::ROLE_ADMIN])
				->setPassword(
					$this->passwordHasher->hashPassword($user, $password)
				);

			$this->userRepo->save($user, true);


			$io->success("User {$user->getEmail()} created");
			$this->transaction->commit();
		} catch (Exception $e) {
			$this->transaction->rollback();
			throw $e;
		}

		return Command::SUCCESS;
	}
}
