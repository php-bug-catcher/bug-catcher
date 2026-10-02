<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Perf;

use BugCatcher\Entity\DurationHistogram;
use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\PerfBucketExtra;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaValidator;
use Zenstruck\Foundry\Test\Factories;

class PerfBucketMappingTest extends KernelTestCase
{
	use Factories;

	private function entityManager(): EntityManagerInterface
	{
		return self::getContainer()->get(EntityManagerInterface::class);
	}

	private function metadata(): ClassMetadata
	{
		return $this->entityManager()->getClassMetadata(PerfBucket::class);
	}

	public function testTheMappingItselfIsSound(): void
	{
		$errors = (new SchemaValidator($this->entityManager()))->validateClass($this->metadata());

		$this->assertSame([], $errors);
	}

	public function testItIsATableOfItsOwnOutsideTheRecordHierarchy(): void
	{
		$metadata = $this->metadata();

		$this->assertSame('perf_bucket', $metadata->getTableName());
		$this->assertFalse($metadata->isInheritanceTypeJoined());
		$this->assertSame(ClassMetadata::GENERATOR_TYPE_IDENTITY, $metadata->generatorType);
		$this->assertSame('bigint', $metadata->getTypeOfField('id'));
	}

	/**
	 * The one constraint both ingest and roll-up depend on: without it a retried batch doubles
	 * every counter instead of converging.
	 */
	public function testTheUniqueKeyCoversEverythingThatIdentifiesABucket(): void
	{
		$constraints = $this->metadata()->table['uniqueConstraints'] ?? [];

		$this->assertArrayHasKey('perf_bucket_uniq', $constraints);
		$this->assertSame(
			['granularity', 'bucket_at', 'project_id', 'server_name', 'host', 'path_hash'],
			$constraints['perf_bucket_uniq']['columns'],
		);
	}

	public function testTheDashboardIndexIsTheWayTheDashboardReads(): void
	{
		$indexes = $this->metadata()->table['indexes'] ?? [];

		$this->assertArrayHasKey('perf_window_idx', $indexes);
		$this->assertSame(['project_id', 'granularity', 'bucket_at'], $indexes['perf_window_idx']['columns']);
	}

	/**
	 * The module has to install on MySQL servers older than 5.7, which have no JSON type at all -
	 * `schema:create` would fail on the first such column. It is also what keeps histogram
	 * arithmetic in the upsert exact: integers, not MySQL's DOUBLE-valued JSON maths.
	 */
	public function testNothingInTheSchemaNeedsAJsonColumn(): void
	{
		foreach ([PerfBucket::class, PerfBucketExtra::class] as $class) {
			$metadata = $this->entityManager()->getClassMetadata($class);

			foreach ($metadata->fieldMappings as $field => $mapping) {
				$this->assertNotSame('json', (string)$mapping['type'], "{$class}::\${$field}");
			}
		}
	}

	public function testTheHistogramIsSixteenCountingColumns(): void
	{
		$metadata = $this->metadata();

		for ($bin = 0; $bin < HistogramBins::COUNT; $bin++) {
			$field = 'durationHistogram.' . DurationHistogram::fieldFor($bin);

			$this->assertSame("bin{$bin}", $metadata->getColumnName($field));
			$this->assertSame('bigint', $metadata->getTypeOfField($field));
		}
	}

	/**
	 * InnoDB before MySQL 5.7 indexes at most 767 bytes of any one column, and the whole key at
	 * most 3072. A `host` of 255 utf8mb4 characters would be 1020 and the unique constraint - the
	 * one the upsert depends on - simply would not be created.
	 */
	public function testEveryIndexedColumnFitsTheOldInnodbLimits(): void
	{
		$metadata = $this->metadata();
		$columns  = $metadata->table['uniqueConstraints']['perf_bucket_uniq']['columns'];
		$total    = 0;

		foreach ($columns as $column) {
			$bytes = 8;
			foreach ($metadata->fieldMappings as $mapping) {
				if ($mapping['columnName'] === $column && isset($mapping['length'])) {
					$bytes = $mapping['length'] * 4;
				}
			}

			$this->assertLessThanOrEqual(767, $bytes, $column);
			$total += $bytes;
		}

		$this->assertLessThanOrEqual(3072, $total);
	}

	/**
	 * `(bucket, name)` and nothing else: a surrogate key would only buy a second index on a table
	 * that holds a row per metric per bucket. Doctrine puts the association last in the primary
	 * key whatever order the mapping declares, which is why reads by bucket lean on the foreign
	 * key's own index rather than on the primary key.
	 */
	public function testAnExtraMetricIsIdentifiedByItsBucketAndItsName(): void
	{
		$metadata = $this->entityManager()->getClassMetadata(PerfBucketExtra::class);

		$this->assertSame('perf_bucket_extra', $metadata->getTableName());
		$identifier = $metadata->getIdentifierFieldNames();
		sort($identifier);
		$this->assertSame(['bucket', 'name'], $identifier);
		$this->assertSame(
			'CASCADE',
			$metadata->getAssociationMapping('bucket')['joinColumns'][0]['onDelete'],
		);
	}

	public function testDeletingAProjectTakesItsBucketsWithIt(): void
	{
		$joinColumn = $this->metadata()->getAssociationMapping('project')['joinColumns'][0];

		$this->assertSame('CASCADE', $joinColumn['onDelete']);
		$this->assertFalse($joinColumn['nullable']);
	}

	/**
	 * Rows are written by DBAL, but they are read as entities, so every column has to survive the
	 * round trip in the type the entity declares - BIGINT as int and not as a numeric string, the
	 * granularity as an enum, the histogram as a list.
	 */
	public function testEveryColumnSurvivesARoundTripThroughTheDatabase(): void
	{
		$project   = ProjectFactory::createOne()->_real();
		$histogram = HistogramBins::empty();
		$histogram[8] = 4;
		$histogram[9] = 1;

		$bucket = new PerfBucket(
			PerfGranularity::Hour,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			$project,
			'web-01',
			'www.site.com',
			'/user/{id}',
			hits: 5,
			sumDuration: 2.19,
			sumUser: 1.8,
			sumSys: 0.12,
			maxDuration: 0.93,
			sumMem: 157286400,
			maxMem: 33554432,
			clientErrors: 1,
			serverErrors: 0,
			durationHistogram: $histogram,
			extra: ['sq' => 42, 'st' => 0.08],
		);

		$em = $this->entityManager();
		$em->persist($bucket);
		$em->flush();
		$id = $bucket->getId();
		$em->clear();

		$this->assertIsInt($id);

		$stored = $em->find(PerfBucket::class, $id);

		$this->assertSame(PerfGranularity::Hour, $stored->getGranularity());
		$this->assertSame('2026-03-10 14:00:00', $stored->getBucketAt()->format('Y-m-d H:i:s'));
		$this->assertSame($project->getId()->toRfc4122(), $stored->getProject()->getId()->toRfc4122());
		$this->assertSame('web-01', $stored->getServerName());
		$this->assertSame('www.site.com', $stored->getHost());
		$this->assertSame('/user/{id}', $stored->getPath());
		$this->assertSame(PerfBucket::hashPath('/user/{id}'), $stored->getPathHash());
		$this->assertSame(5, $stored->getHits());
		$this->assertSame(2.19, $stored->getSumDuration());
		$this->assertSame(1.8, $stored->getSumUser());
		$this->assertSame(0.12, $stored->getSumSys());
		$this->assertSame(0.93, $stored->getMaxDuration());
		$this->assertSame(157286400, $stored->getSumMem());
		$this->assertSame(33554432, $stored->getMaxMem());
		$this->assertSame(1, $stored->getClientErrors());
		$this->assertSame(0, $stored->getServerErrors());
		$this->assertSame($histogram, $stored->getDurationHistogram());
		$this->assertSame(['sq' => 42.0, 'st' => 0.08], $stored->getExtra());
	}

	public function testTheUniqueKeyIsEnforcedByTheDatabaseAndNotOnlyByTheMapping(): void
	{
		$project = ProjectFactory::createOne()->_real();
		$em      = $this->entityManager();

		$em->persist($this->minuteBucket($project));
		$em->flush();

		$this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

		$em->persist($this->minuteBucket($project));
		$em->flush();
	}

	private function minuteBucket(object $project): PerfBucket
	{
		return new PerfBucket(
			PerfGranularity::Minute,
			new DateTimeImmutable('2026-03-10 14:37:00'),
			$project,
			'web-01',
			'www.site.com',
			'/user/{id}',
		);
	}
}
