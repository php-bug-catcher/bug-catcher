<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Service;

use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Enum\RecordEventType;
use BugCatcher\Event\RecordEvent;
use BugCatcher\Service\BatchRecordDeleteInterface;
use BugCatcher\Service\BatchRecordDeleteService;
use BugCatcher\Tests\App\Entity\RecordCron;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordLogFactory;
use BugCatcher\Tests\App\Factory\RecordLogTraceFactory;
use BugCatcher\Tests\App\Factory\RecordPingFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Zenstruck\Foundry\Test\Factories;

/**
 * "Fix selected" deletes the selected records with one statement across the joined tables, so
 * every subtype in the discriminator map has to be named in it. Miss one and the rows of that
 * subtype's own table outlive the record they belonged to - or, since the guard was added, the
 * button throws instead.
 */
class BatchRecordDeleteServiceTest extends KernelTestCase
{
	use Factories;

	/**
	 * The guard is the whole safety net of the extension point, and it is only as good as the
	 * list behind it.
	 */
	public function testEverySubtypeOfTheHierarchyIsHandled(): void
	{
		$service = self::getContainer()->get(BatchRecordDeleteInterface::class);
		$project = ProjectFactory::createOne()->_real();

		// no exception is the assertion: deleteByIds() refuses to run while anything in the
		// discriminator map is unaccounted for
		$service->deleteByIds([RecordLogFactory::createOne()->_real()->getId()->toBinary()], [$project]);

		$this->addToAssertionCount(1);
	}

	public function testARecordOfEverySubtypeGoesWithItsOwnTableRow(): void
	{
		$project = ProjectFactory::createOne()->_real();
		$em      = self::getContainer()->get(EntityManagerInterface::class);

		$records = [
			'record_log'        => RecordLogFactory::createOne(['project' => $project])->_real(),
			'record_log_trace'  => RecordLogTraceFactory::createOne(['project' => $project])->_real(),
			'record_ping'       => RecordPingFactory::createOne(['project' => $project])->_real(),
			'record_performance' => $this->performance($project),
			'record_cron'       => $this->cron($project),
		];

		// record_log holds the trace as well: a joined subclass writes a row in every table of
		// its ancestry, which is the reason this statement has to join them all
		$this->assertSame(5, $this->rows('record'), 'before');
		foreach (array_keys($records) as $table) {
			$this->assertGreaterThan(0, $this->rows($table), "{$table} before");
		}

		self::getContainer()->get(BatchRecordDeleteInterface::class)->deleteByIds(
			array_map(static fn(Record $record): string => $record->getId()->toBinary(), array_values($records)),
			[$project],
		);
		$em->clear();

		foreach (array_keys($records) as $table) {
			$this->assertSame(0, $this->rows($table), "{$table} after");
		}
		$this->assertSame(0, $this->rows('record'));
	}

	public function testDeletingNothingIsNotAStatement(): void
	{
		$log = RecordLogFactory::createOne()->_real();

		self::getContainer()->get(BatchRecordDeleteInterface::class)->deleteByIds([], []);

		$this->assertSame(1, $this->rows('record'));
		$this->assertNotNull($log->getId());
	}

	public function testTheDashboardIsToldThatRecordsWentAway(): void
	{
		$project = ProjectFactory::createOne()->_real();
		$log     = RecordLogFactory::createOne(['project' => $project])->_real();

		$seen = [];
		self::getContainer()->get(EventDispatcherInterface::class)->addListener(
			RecordEvent::class,
			static function (RecordEvent $event) use (&$seen): void {
				$seen[] = $event->type;
			},
		);

		self::getContainer()->get(BatchRecordDeleteInterface::class)
			->deleteByIds([$log->getId()->toBinary()], [$project]);

		$this->assertSame([RecordEventType::BATCH_DELETED], $seen);
	}

	/**
	 * What an application that adds a record type sees if it skips the step in
	 * docs/custom_record.md - a readable refusal rather than orphaned rows.
	 */
	public function testASubtypeNobodyHandlesStopsTheDeletion(): void
	{
		$project = ProjectFactory::createOne()->_real();
		$log     = RecordLogFactory::createOne(['project' => $project])->_real();

		// the bundle's own service does not know about the test application's RecordCron
		$service = new BatchRecordDeleteService(
			self::getContainer()->get(EntityManagerInterface::class),
			new EventDispatcher(),
		);

		$this->expectException(LogicException::class);
		$this->expectExceptionMessageMatches('/RecordCron/');

		$service->deleteByIds([$log->getId()->toBinary()], [$project]);
	}

	private function performance(object $project): RecordPerformance
	{
		$record = new RecordPerformance(
			$project,
			'/checkout',
			'p95',
			PerfUnit::Milliseconds,
			210.0,
			3100.0,
			new DateTimeImmutable('2026-03-10 14:35:00'),
		);
		$record->setHash($record->calculateHash());

		return $this->persist($record);
	}

	private function cron(object $project): RecordCron
	{
		$record = new RecordCron();
		$record->setProject($project)
			->setCommand('app:something')
			->setLastStart(new DateTimeImmutable('-10 minutes'))
			->setInterval(5)
			->setEstimated(60);
		$record->setHash($record->calculateHash());

		return $this->persist($record);
	}

	/** @template T of Record @param T $record @return T */
	private function persist(Record $record): Record
	{
		$em = self::getContainer()->get(EntityManagerInterface::class);
		$em->persist($record);
		$em->flush();

		return $record;
	}

	private function rows(string $table): int
	{
		return (int)$this->connection()->fetchOne("SELECT COUNT(*) FROM {$table}");
	}

	private function connection(): Connection
	{
		return self::getContainer()->get(EntityManagerInterface::class)->getConnection();
	}
}
