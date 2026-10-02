<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Perf;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Enum\RecordEventType;
use BugCatcher\Event\RecordEvent;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\Detection\AnomalyFinding;
use BugCatcher\Service\Perf\Detection\DetectionRunner;
use BugCatcher\Service\Perf\Detection\PerfDetectorInterface;
use BugCatcher\Service\Perf\Detection\RecordPerformanceWriter;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\PerfWindow;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Zenstruck\Foundry\Test\Factories;

class DetectionRunnerTest extends KernelTestCase
{
	use Factories;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne(['enabled' => true])->_real();
	}

	public function testEveryFindingBecomesARecord(): void
	{
		$this->measured();

		$result = $this->runner([$this->detector('regression', ['/checkout', '/cart'])])->run($this->window());

		$this->assertSame(1, $result->projects);
		$this->assertSame(2, $result->findings);

		$records = $this->records();
		$this->assertCount(2, $records);
		$this->assertSame(['/cart', '/checkout'], array_map(
			static fn(RecordPerformance $record): string => $record->getPath(),
			$records,
		));

		$record = $records[1];
		$this->assertSame('p95', $record->getMetric());
		$this->assertSame(PerfUnit::Milliseconds, $record->getUnit());
		$this->assertSame(210.0, $record->getBaseline());
		$this->assertSame(3100.0, $record->getObserved());
		$this->assertSame('2026-03-10 14:35:00', $record->getWindowAt()->format('Y-m-d H:i:s'));
		$this->assertSame($this->project->getId()->toRfc4122(), $record->getProject()->getId()->toRfc4122());
	}

	/**
	 * The hash is what makes the dashboard show one row with a count rather than one row per
	 * five-minute run, and nothing else in the write path sets it.
	 */
	public function testTheRecordIsStoredTheWayTheDashboardReadsIt(): void
	{
		$this->measured();

		$this->runner([$this->detector('regression', ['/checkout'])])->run($this->window());

		$record = $this->records()[0];
		$this->assertNotNull($record->getHash());
		$this->assertSame($record->calculateHash(), $record->getHash());
		$this->assertSame('new', $record->getStatus());
		$this->assertNotNull($record->getDate());
	}

	/** Which is the whole of what the design means by "the notifiers fire for free". */
	public function testTheNotifiersAreToldThatARecordAppeared(): void
	{
		$this->measured();

		$seen       = [];
		$dispatcher = new EventDispatcher();
		$dispatcher->addListener(RecordEvent::class, static function (RecordEvent $event) use (&$seen): void {
			$seen[] = [$event->type, $event->record?->getHash()];
		});

		$this->runner([$this->detector('regression', ['/checkout'])], $dispatcher)->run($this->window());

		$this->assertCount(1, $seen);
		$this->assertSame(RecordEventType::CREATED, $seen[0][0]);
		$this->assertNotNull($seen[0][1]);
	}

	public function testEveryEnabledDetectorRuns(): void
	{
		$this->measured();

		$result = $this->runner([
			$this->detector('regression', ['/checkout']),
			$this->detector('slo', ['/cart']),
		])->run($this->window());

		$this->assertSame(2, $result->findings);
		$this->assertSame(['/cart', '/checkout'], array_map(
			static fn(RecordPerformance $record): string => $record->getPath(),
			$this->records(),
		));
	}

	public function testAProjectNobodyMonitorsAnyMoreIsNotDetectedOn(): void
	{
		$this->measured();
		$this->project->setEnabled(false);
		self::getContainer()->get(EntityManagerInterface::class)->flush();

		$result = $this->runner([$this->detector('regression', ['/checkout'])])->run($this->window());

		$this->assertSame(0, $result->projects);
		$this->assertSame([], $this->records());
	}

	public function testAProjectThatMeasuredNothingInTheWindowIsNotDetectedOn(): void
	{
		$result = $this->runner([$this->detector('regression', ['/checkout'])])->run($this->window());

		$this->assertSame(0, $result->projects);
		$this->assertSame(0, $result->findings);
	}

	public function testADetectorThatFindsNothingWritesNothing(): void
	{
		$this->measured();

		$result = $this->runner([$this->detector('regression', [])])->run($this->window());

		$this->assertSame(1, $result->projects);
		$this->assertSame(0, $result->findings);
		$this->assertSame([], $this->records());
	}

	/** @param list<PerfDetectorInterface> $detectors */
	private function runner(array $detectors, ?EventDispatcherInterface $dispatcher = null): DetectionRunner
	{
		$em = self::getContainer()->get(EntityManagerInterface::class);

		return new DetectionRunner(
			self::getContainer()->get(PerfBucketRepository::class),
			new RecordPerformanceWriter($em, $dispatcher ?? new EventDispatcher()),
			$detectors,
			new NullLogger(),
		);
	}

	/**
	 * A detector that reports the routes it was given - the runner's job is what is under test,
	 * not the arithmetic of detection.
	 *
	 * @param list<string> $paths
	 */
	private function detector(string $name, array $paths): PerfDetectorInterface
	{
		return new class ($name, $paths) implements PerfDetectorInterface {
			/** @param list<string> $paths */
			public function __construct(private readonly string $name, private readonly array $paths)
			{
			}

			public function name(): string
			{
				return $this->name;
			}

			public function detect(Project $project, PerfWindow $window): iterable
			{
				foreach ($this->paths as $path) {
					yield new AnomalyFinding(
						$project,
						$path,
						'p95',
						PerfUnit::Milliseconds,
						210.0,
						3100.0,
						$window->from,
					);
				}
			}
		};
	}

	private function window(): PerfWindow
	{
		return new PerfWindow(
			new DateTimeImmutable('2026-03-10 14:35:00'),
			new DateTimeImmutable('2026-03-10 14:40:00'),
			PerfGranularity::Minute,
		);
	}

	private function measured(): void
	{
		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				PerfGranularity::Minute,
				new DateTimeImmutable('2026-03-10 14:36:00'),
				$this->project,
				'web-01',
				'www.site.com',
				'/checkout',
				hits: 40,
			),
		]);
	}

	/** @return list<RecordPerformance> */
	private function records(): array
	{
		$em = self::getContainer()->get(EntityManagerInterface::class);
		$em->clear();

		return $em->getRepository(RecordPerformance::class)->findBy([], ['path' => 'ASC']);
	}
}
