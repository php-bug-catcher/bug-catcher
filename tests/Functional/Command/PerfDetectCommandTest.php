<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Functional\Command;

use BugCatcher\Command\PerfDetectCommand;
use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Detection\DetectionRunner;
use BugCatcher\Service\Perf\Detection\DetectionWindowResolver;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;

class PerfDetectCommandTest extends KernelTestCase
{
	use Factories;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne(['enabled' => true])->_real();
	}

	/**
	 * End to end through the container: the configured metric, the baseline out of the hour
	 * buckets, the threshold, and a record at the other end. Everything is placed relative to now
	 * because the window the command looks at is the last completed one.
	 */
	public function testARouteThatRegressedEndsUpAsARecord(): void
	{
		$window = $this->lastCompletedWindow(5);

		foreach ([1, 2, 3, 4] as $week) {
			$this->store(
				PerfGranularity::Hour,
				$window->sub(new DateInterval('P' . 7 * $week . 'D')),
				hits: 40,
				msPerHit: 100,
			);
		}
		$this->store(PerfGranularity::Minute, $window->add(new DateInterval('PT1M')), hits: 40, msPerHit: 4000);

		$tester = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertStringContainsString('1 regression', $tester->getDisplay());

		$records = $this->records();
		$this->assertCount(1, $records);
		$this->assertSame('/checkout', $records[0]->getPath());
		$this->assertSame('p95', $records[0]->getMetric());
	}

	public function testAQuietWindowRecordsNothing(): void
	{
		$window = $this->lastCompletedWindow(5);
		$this->store(PerfGranularity::Minute, $window->add(new DateInterval('PT1M')), hits: 40, msPerHit: 100);

		$tester = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertSame([], $this->records());
	}

	public function testTheWindowIsAsWideAsItWasAskedToBe(): void
	{
		$tester = $this->runCommand(['--window' => '15']);

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertStringContainsString('15 minutes', $tester->getDisplay());
	}

	public function testAWindowThatIsNotAWindowIsRefused(): void
	{
		$this->assertSame(Command::INVALID, $this->runCommand(['--window' => '0'])->getStatusCode());
	}

	public function testItRefusesToRunWhilePerformanceMonitoringIsOff(): void
	{
		$container = self::getContainer();
		$command   = new PerfDetectCommand(
			$container->get(DetectionWindowResolver::class),
			$container->get(DetectionRunner::class),
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
		$command = (new Application(self::$kernel))->find('app:perf:detect');
		$tester  = new CommandTester($command);
		$tester->execute($input);

		return $tester;
	}

	private function lastCompletedWindow(int $minutes): DateTimeImmutable
	{
		return PerfGranularity::Minute->floor(new DateTimeImmutable())
			->sub(new DateInterval('PT' . $minutes . 'M'));
	}

	private function store(PerfGranularity $granularity, DateTimeImmutable $at, int $hits, int $msPerHit): void
	{
		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				$granularity,
				$at,
				$this->project,
				'web-01',
				'www.site.com',
				'/checkout',
				hits: $hits,
				sumDuration: $hits * $msPerHit / 1000,
				durationHistogram: $this->histogram($hits, $msPerHit),
			),
		]);
	}

	/** Every hit in the bin its duration belongs to, so the p95 comes out where it should. */
	private function histogram(int $hits, int $msPerHit): array
	{
		$histogram = \BugCatcher\Service\Perf\Histogram\HistogramBins::empty();
		$histogram[\BugCatcher\Service\Perf\Histogram\HistogramBins::binFor((float)$msPerHit)] = $hits;

		return $histogram;
	}

	/** @return list<RecordPerformance> */
	private function records(): array
	{
		$em = self::getContainer()->get(EntityManagerInterface::class);
		$em->clear();

		return $em->getRepository(RecordPerformance::class)->findAll();
	}
}
