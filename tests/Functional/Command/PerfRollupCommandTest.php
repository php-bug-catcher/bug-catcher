<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Functional\Command;

use BugCatcher\Command\PerfRollupCommand;
use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\Rollup\RollupService;
use BugCatcher\Service\Perf\Rollup\RollupWindowResolver;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;

class PerfRollupCommandTest extends KernelTestCase
{
	use Factories;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne()->_real();
	}

	/** The command is a shell: that it is wired at all is most of what there is to test. */
	public function testItComputesTheHoursItWasAskedFor(): void
	{
		$this->store(PerfGranularity::Minute, '2026-03-10 14:03:00', hits: 5);
		$this->store(PerfGranularity::Minute, '2026-03-10 14:37:00', hits: 3);

		$tester = $this->runCommand(['--granularity' => 'hour', '--from' => '2026-03-10 14:00:00', '--to' => '2026-03-10 14:00:00']);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertStringContainsString('1 buckets, 1 rows', $tester->getDisplay());
		$this->assertSame(8, $this->stored(PerfGranularity::Hour, '2026-03-10 14:00:00')->getHits());
	}

	public function testItComputesDaysOutOfHours(): void
	{
		$this->store(PerfGranularity::Hour, '2026-03-10 09:00:00', hits: 7);

		$tester = $this->runCommand(['--granularity' => 'day', '--from' => '2026-03-10', '--to' => '2026-03-10']);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertSame(7, $this->stored(PerfGranularity::Day, '2026-03-10 00:00:00')->getHits());
	}

	public function testWithoutAWindowItTakesTheLastCompletedBucket(): void
	{
		$lastHour = (new DateTimeImmutable('-1 hour'))->setTime((int)(new DateTimeImmutable('-1 hour'))->format('G'), 17);
		$this->store(PerfGranularity::Minute, $lastHour->format('Y-m-d H:i:s'), hits: 4);

		$tester = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertSame(4, $this->stored(PerfGranularity::Hour, $lastHour->format('Y-m-d H:i:s'))->getHits());
	}

	/** @dataProvider unusableInput */
	public function testInputThatCannotBeARollUpIsRefusedWithoutTouchingTheTable(array $input): void
	{
		$tester = $this->runCommand($input);

		$this->assertSame(Command::INVALID, $tester->getStatusCode());
	}

	/** @return array<string, array{array<string, string>}> */
	public static function unusableInput(): array
	{
		return [
			'nothing rolls up into minutes' => [['--granularity' => 'minute']],
			'no such granularity'           => [['--granularity' => 'week']],
			'not a date'                    => [['--from' => 'last tuesdya']],
			'ends before it starts'         => [['--from' => '2026-03-10', '--to' => '2026-03-01']],
		];
	}

	/**
	 * Cron lines outlive the configuration that switched the module off, so the refusal is loud:
	 * a silent success would hide a dashboard that quietly stopped being filled.
	 */
	public function testItRefusesToRunWhilePerformanceMonitoringIsOff(): void
	{
		$container = self::getContainer();
		$command   = new PerfRollupCommand(
			$container->get(RollupWindowResolver::class),
			$container->get(RollupService::class),
			enabled: false,
		);

		$tester = new CommandTester($command);
		$tester->execute([]);

		$this->assertSame(Command::FAILURE, $tester->getStatusCode());
		$this->assertStringContainsString('perf.enabled', $tester->getDisplay());
	}

	/** @param array<string, string> $input */
	private function runCommand(array $input): CommandTester
	{
		self::bootKernel();
		$command = (new Application(self::$kernel))->find('app:perf:rollup');
		$tester  = new CommandTester($command);
		$tester->execute($input);

		return $tester;
	}

	private function store(PerfGranularity $granularity, string $bucketAt, int $hits): void
	{
		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				$granularity,
				new DateTimeImmutable($bucketAt),
				$this->project,
				'web-01',
				'www.site.com',
				'/user/{id}',
				hits: $hits,
			),
		]);
	}

	private function stored(PerfGranularity $granularity, string $bucketAt): ?PerfBucket
	{
		$container = self::getContainer();
		$container->get(EntityManagerInterface::class)->clear();

		return $container->get(PerfBucketRepository::class)->findOneByKey(
			$granularity,
			new DateTimeImmutable($bucketAt),
			$this->project,
			'web-01',
			'www.site.com',
			'/user/{id}',
		);
	}
}
