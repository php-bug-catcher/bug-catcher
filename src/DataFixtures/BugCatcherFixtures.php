<?php

namespace BugCatcher\DataFixtures;

use App\Factory\ProjectFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use BugCatcher\Factory\LogRecordFactory;
use BugCatcher\Factory\PingRecordFactory;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class BugCatcherFixtures extends Fixture {
	public function load(ObjectManager $manager): void {
		/** @var Connection $object */
		$object = $manager->getConnection();
		$object->getConfiguration()->setSQLLogger(null);
		$object->getConfiguration()->setMiddlewares([]);
		$output = new ConsoleOutput();
		$io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

		$io->writeln("creating projects");
		ProjectFactory::createMany(8);
		$io->writeln("creating pings");
		$io->progressStart(1000);
		PingRecordFactory::createMany(1000, static function (int $i) use ($io) {
			$io->progressAdvance(1);

			return [];
		});
		$io->progressFinish();
		$io->writeln("");
		$io->writeln("creating logs");
		$io->progressStart(1000);
		LogRecordFactory::createMany(1000, static function (int $i) use ($io) {
			$io->progressAdvance(1);

			return [];
		});
		$io->progressFinish();

		$manager->flush();
	}
}
