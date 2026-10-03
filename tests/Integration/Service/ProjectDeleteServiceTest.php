<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Service;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Service\BatchRecordDeleteInterface;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\Retention\ChunkedDeleter;
use BugCatcher\Service\ProjectDeleteService;
use BugCatcher\Tests\App\Factory\NotifierFaviconFactory;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordCronFactory;
use BugCatcher\Tests\App\Factory\RecordLogFactory;
use BugCatcher\Tests\App\Factory\RecordLogTraceFactory;
use BugCatcher\Tests\App\Factory\RecordLogWithholderFactory;
use BugCatcher\Tests\App\Factory\RecordPingFactory;
use BugCatcher\Tests\App\Factory\UserFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\Factories;

/**
 * Deleting a project has to take its records with it. `remove()` on its own cannot: the withholder
 * foreign key has no cascade and refuses the statement, and where the database does cascade it is
 * deleting rows the ORM never saw.
 */
class ProjectDeleteServiceTest extends KernelTestCase
{
	use Factories;

	public function testEverythingCollectedUnderAProjectGoesWithIt(): void
	{
		$project = ProjectFactory::createOne()->_real();

		RecordLogFactory::createOne(['project' => $project]);
		RecordLogTraceFactory::createOne(['project' => $project]);
		RecordPingFactory::createOne(['project' => $project]);
		RecordCronFactory::createOne(['project' => $project]);
		$this->performance($project);
		RecordLogWithholderFactory::createOne(['project' => $project]);
		$this->perfBucket($project);
		UserFactory::createOne(['projects' => [$project]]);
		NotifierFaviconFactory::createOne(['projects' => [$project]]);

		$tables = [
			'record',
			'record_log',
			'record_log_trace',
			'record_ping',
			'record_cron',
			'record_performance',
			'record_log_withholder',
			'perf_bucket',
			'perf_bucket_extra',
			'user_project',
			'notifier_project',
			'project',
		];
		foreach ($tables as $table) {
			$this->assertGreaterThan(0, $this->rows($table), "{$table} before");
		}

		$this->service()->delete($project);
		self::getContainer()->get(EntityManagerInterface::class)->clear();

		foreach ($tables as $table) {
			$this->assertSame(0, $this->rows($table), "{$table} after");
		}
	}

	public function testOnlyTheDeletedProjectIsTouched(): void
	{
		$doomed   = ProjectFactory::createOne()->_real();
		$survivor = ProjectFactory::createOne()->_real();

		RecordLogFactory::createOne(['project' => $doomed]);
		RecordLogFactory::createOne(['project' => $survivor]);
		RecordLogWithholderFactory::createOne(['project' => $doomed]);
		RecordLogWithholderFactory::createOne(['project' => $survivor]);
		$this->perfBucket($doomed);
		$this->perfBucket($survivor);

		$this->service()->delete($doomed);
		$em = self::getContainer()->get(EntityManagerInterface::class);
		$em->clear();

		$this->assertSame(1, $this->rows('record'));
		$this->assertSame(1, $this->rows('record_log_withholder'));
		$this->assertSame(1, $this->rows('perf_bucket'));
		$this->assertCount(1, $em->getRepository(Project::class)->findAll());
	}

	/**
	 * The records are deleted a chunk at a time, so the loop that drains them is the part that can
	 * silently stop one chunk short.
	 */
	public function testRecordsBeyondOneChunkGoToo(): void
	{
		$project = ProjectFactory::createOne()->_real();
		RecordLogFactory::createMany(5, ['project' => $project]);

		$this->service(chunkSize: 2)->delete($project);

		$this->assertSame(0, $this->rows('record'));
		$this->assertSame(0, $this->rows('record_log'));
	}

	private function service(int $chunkSize = ProjectDeleteService::DEFAULT_CHUNK_SIZE): ProjectDeleteService
	{
		return new ProjectDeleteService(
			self::getContainer()->get(EntityManagerInterface::class),
			// the test application's implementation, which knows about RecordCron as well
			self::getContainer()->get(BatchRecordDeleteInterface::class),
			new ChunkedDeleter($chunkSize),
			$chunkSize,
		);
	}

	private function performance(Project $project): void
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

		$em = self::getContainer()->get(EntityManagerInterface::class);
		$em->persist($record);
		$em->flush();
	}

	/** With one extra metric, so the side table is covered as well. */
	private function perfBucket(Project $project): void
	{
		$bucket = new PerfBucket(
			PerfGranularity::Minute,
			new DateTimeImmutable('2026-03-10 14:37:00'),
			$project,
			'web-01',
			'www.site.com',
			'/user/{id}',
			hits: 3,
			extra: ['queries' => 12],
		);

		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([$bucket]);
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
