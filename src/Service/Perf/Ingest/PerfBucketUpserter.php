<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Ingest;

use BugCatcher\Entity\DurationHistogram;
use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\PerfBucketExtra;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use InvalidArgumentException;
use LogicException;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * The only thing that writes `perf_bucket`, for ingest and for roll-up alike.
 *
 * `INSERT ... ON DUPLICATE KEY UPDATE` against the unique key, so a second write for a bucket
 * lands on the row that is already there rather than opening another one. What that second write
 * *means* is the caller's to say, and {@see UpsertMode} is where it says it: ingest only ever
 * holds part of a minute and therefore adds, while the roll-up rebuilds a whole hour out of every
 * minute in it and therefore overwrites. Adding is what lets a line the hook wrote in shutdown
 * reach the minute it started in; overwriting is what makes a repeated roll-up a no-op.
 *
 * **The known limit of adding:** if the server commits a batch and the 2xx never reaches the
 * collector, the collector's cursor does not move and the batch is sent again - and then the
 * counters really do double. Narrowing that window is why the whole batch goes in one
 * transaction; closing it would need an idempotency token per batch, which neither the design nor
 * the wire format has. A lost response costs one minute of inflated numbers on one route.
 *
 * All arithmetic is plain SQL on scalar columns, with no JSON type and no JSON function anywhere:
 * the bundle supports MySQL servers older than 5.7. Extra metrics therefore live in
 * `perf_bucket_extra`, one row per name, which also means a client-controlled metric name is a
 * query parameter rather than a string built into a JSON path.
 *
 * MySQL only, like the rest of the bundle (see the MySQL DQL functions in `doctrine.dql`). Column
 * names come from the mapping rather than from string literals, because the application owns the
 * naming strategy.
 */
final class PerfBucketUpserter
{
	/** Columns a second batch adds to. */
	private const array ADDITIVE = [
		'hits',
		'sumDuration',
		'sumUser',
		'sumSys',
		'sumMem',
		'clientErrors',
		'serverErrors',
	];

	/**
	 * Columns where the greater of the two wins. A maximum is a measurement of one request, so
	 * adding them would be a lie and overwriting them would lose the peak.
	 */
	private const array GREATEST = ['maxDuration', 'maxMem'];

	/** @var array<string, string> the statement per {@see UpsertMode}, built once */
	private array $bucketSql = [];

	/** @var array<string, string> */
	private array $extraSql = [];

	public function __construct(private readonly EntityManagerInterface $em)
	{
	}

	/**
	 * @param iterable<PerfBucket> $buckets rows to write into the table, in any order
	 * @param UpsertMode $mode what a row already there means - see the enum
	 * @return int how many rows were written
	 */
	public function upsert(iterable $buckets, UpsertMode $mode = UpsertMode::Add): int
	{
		$connection = $this->em->getConnection();
		$this->assertMysql($connection);

		return $connection->transactional(function (Connection $connection) use ($buckets, $mode): int {
			$written = 0;
			foreach ($buckets as $bucket) {
				$this->upsertBucket($connection, $bucket, $mode);
				$this->upsertExtra($connection, $bucket, $mode);
				$written++;
			}

			return $written;
		});
	}

	private function upsertBucket(Connection $connection, PerfBucket $bucket, UpsertMode $mode): void
	{
		$params = [
			$bucket->getGranularity()->value,
			$bucket->getBucketAt(),
			$bucket->getProject()->getId(),
			$bucket->getServerName(),
			$bucket->getHost(),
			$bucket->getPathHash(),
			$bucket->getPath(),
		];
		$types = [1 => Types::DATETIME_IMMUTABLE, 2 => UuidType::NAME];

		$measurements = [
			...array_map(fn(string $field) => $this->valueOf($bucket, $field), [...self::ADDITIVE, ...self::GREATEST]),
			...$bucket->getDurationHistogram(),
		];

		// The measurements twice: once for the INSERT, once for the UPDATE. Writing them out
		// rather than using VALUES() keeps the statement off a MySQL extension that 8.0.20
		// deprecated and whose replacement MariaDB does not have.
		$connection->executeStatement(
			$this->bucketSql[$mode->name] ??= $this->buildBucketSql($mode),
			[...$params, ...$measurements, ...$measurements],
			$types,
		);
	}

	/**
	 * Extra metrics are rows of their own, keyed by `(bucket, name)`, so the bucket's id is needed
	 * - and an upsert does not say whether it inserted or updated. `id = LAST_INSERT_ID(id)` in the
	 * UPDATE half is the standard MySQL answer: on the insert path `lastInsertId()` is the new
	 * auto-increment, on the duplicate path it is the id of the row that was already there.
	 */
	private function upsertExtra(Connection $connection, PerfBucket $bucket, UpsertMode $mode): void
	{
		$extra = $this->extraOf($bucket);
		if ($extra === []) {
			return;
		}

		$bucketId = (int)$connection->lastInsertId();
		$sql      = $this->extraSql[$mode->name] ??= $this->buildExtraSql($mode);

		foreach ($extra as $name => $value) {
			$connection->executeStatement($sql, [$bucketId, $name, $value, $value]);
		}
	}

	private function buildBucketSql(UpsertMode $mode): string
	{
		$metadata = $this->em->getClassMetadata(PerfBucket::class);

		$insert = [
			$this->column($metadata, 'granularity'),
			$this->column($metadata, 'bucketAt'),
			$metadata->getSingleAssociationJoinColumnName('project'),
			$this->column($metadata, 'serverName'),
			$this->column($metadata, 'host'),
			$this->column($metadata, 'pathHash'),
			$this->column($metadata, 'path'),
		];
		$update = [];

		$add      = $mode === UpsertMode::Add;
		$additive = $add ? '%1$s = %1$s + ?' : '%1$s = ?';

		foreach (self::ADDITIVE as $field) {
			$column   = $this->column($metadata, $field);
			$insert[] = $column;
			$update[] = sprintf($additive, $column);
		}
		foreach (self::GREATEST as $field) {
			$column   = $this->column($metadata, $field);
			$insert[] = $column;
			$update[] = sprintf($add ? '%1$s = GREATEST(%1$s, ?)' : '%1$s = ?', $column);
		}
		for ($bin = 0; $bin < HistogramBins::COUNT; $bin++) {
			$column   = $this->column($metadata, 'durationHistogram.' . DurationHistogram::fieldFor($bin));
			$insert[] = $column;
			$update[] = sprintf($additive, $column);
		}

		// `path` is deliberately not updated: the key holds its hash, so an existing row already
		// has the path this one would write. `id` is assigned so that lastInsertId() answers for
		// the duplicate path too - see upsertExtra().
		$update[] = sprintf('%1$s = LAST_INSERT_ID(%1$s)', $this->column($metadata, 'id'));

		return sprintf(
			'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
			$metadata->getTableName(),
			implode(', ', $insert),
			implode(', ', array_fill(0, count($insert), '?')),
			implode(', ', $update),
		);
	}

	private function buildExtraSql(UpsertMode $mode): string
	{
		$metadata = $this->em->getClassMetadata(PerfBucketExtra::class);
		$value    = $this->column($metadata, 'value');

		return sprintf(
			'INSERT INTO %s (%s, %s, %s) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE %s',
			$metadata->getTableName(),
			$metadata->getSingleAssociationJoinColumnName('bucket'),
			$this->column($metadata, 'name'),
			$value,
			$mode === UpsertMode::Add
				? sprintf('%1$s = %1$s + ?', $value)
				: sprintf('%s = ?', $value),
		);
	}

	/**
	 * Metric names come from the monitored application. The statement binds them as parameters, so
	 * this is not about SQL - it is about `perf_bucket_extra.name` being 32 characters of
	 * something a dashboard can print and a configuration file can refer to.
	 *
	 * @return array<string, float>
	 */
	private function extraOf(PerfBucket $bucket): array
	{
		$extra = $bucket->getExtra();

		foreach (array_keys($extra) as $name) {
			if (!is_string($name) || preg_match(PerfBucket::EXTRA_NAME_PATTERN, $name) !== 1) {
				throw new InvalidArgumentException(sprintf(
					'Extra metric name %s is not usable: letters, digits, underscore, dot and dash, up to 32 characters.',
					var_export($name, true),
				));
			}
		}

		return $extra;
	}

	private function valueOf(PerfBucket $bucket, string $field): int|float
	{
		return match ($field) {
			'hits'         => $bucket->getHits(),
			'sumDuration'  => $bucket->getSumDuration(),
			'sumUser'      => $bucket->getSumUser(),
			'sumSys'       => $bucket->getSumSys(),
			'sumMem'       => $bucket->getSumMem(),
			'clientErrors' => $bucket->getClientErrors(),
			'serverErrors' => $bucket->getServerErrors(),
			'maxDuration'  => $bucket->getMaxDuration(),
			'maxMem'       => $bucket->getMaxMem(),
		};
	}

	private function column(ClassMetadata $metadata, string $field): string
	{
		return $metadata->getColumnName($field);
	}

	private function assertMysql(Connection $connection): void
	{
		if (!$connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
			throw new LogicException(sprintf(
				'%s needs MySQL: it upserts with ON DUPLICATE KEY UPDATE and reads back the row id with LAST_INSERT_ID().',
				self::class,
			));
		}
	}
}
