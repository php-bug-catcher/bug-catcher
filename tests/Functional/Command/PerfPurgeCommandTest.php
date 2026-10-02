<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Functional\Command;

use BugCatcher\Command\PerfPurgeCommand;
use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\Retention\PurgeService;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;

class PerfPurgeCommandTest extends KernelTestCase
{
	use Factories;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne()->_real();
	}

	public function testItDeletesWhatTheRetentionNoLongerKeeps(): void
	{
		$this->store('-8 days');
		$this->store('-1 day');

		$tester = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertStringContainsString('deleted 1 rows', $tester->getDisplay());
		$this->assertSame(1, $this->storedRows());
	}

	public function testADryRunSaysWhatItWouldDeleteAndDeletesNothing(): void
	{
		$this->store('-8 days');

		$tester = $this->runCommand(['--dry-run' => true]);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertStringContainsString('would delete 1 rows', $tester->getDisplay());
		$this->assertSame(1, $this->storedRows());
	}

	public function testItRefusesToRunWhilePerformanceMonitoringIsOff(): void
	{
		$command = new PerfPurgeCommand(self::getContainer()->get(PurgeService::class), enabled: false);
		$tester  = new CommandTester($command);
		$tester->execute([]);

		$this->assertSame(Command::FAILURE, $tester->getStatusCode());
		$this->assertStringContainsString('perf.enabled', $tester->getDisplay());
	}

	/** @param array<string, mixed> $input */
	private function runCommand(array $input): CommandTester
	{
		self::bootKernel();
		$command = (new Application(self::$kernel))->find('app:perf:purge');
		$tester  = new CommandTester($command);
		$tester->execute($input);

		return $tester;
	}

	private function store(string $ago): void
	{
		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				PerfGranularity::Minute,
				new DateTimeImmutable($ago),
				$this->project,
				'web-01',
				'www.site.com',
				'/user/{id}',
				hits: 1,
			),
		]);
	}

	private function storedRows(): int
	{
		return (int)self::getContainer()
			->get(EntityManagerInterface::class)
			->getConnection()
			->fetchOne('SELECT COUNT(*) FROM perf_bucket');
	}
}
