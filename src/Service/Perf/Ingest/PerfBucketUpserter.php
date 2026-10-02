<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Ingest;

use BugCatcher\Entity\PerfBucket;
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
 * `INSERT ... ON DUPLICATE KEY UPDATE` against the unique key, with counters adding, maxima taking
 * `GREATEST` and histograms adding bin by bin. That is what lets a bucket be completed by a later
 * batch: the hook writes its line in shutdown, so a request that started inside a minute can be
 * logged after that minute was already shipped, and a batch the roll-up re-runs over has to land
 * on the same numbers rather than a second row.
 *
 * **The known limit of adding:** if the server commits a batch and the 2xx never reaches the
 * collector, the collector's cursor does not move and the batch is sent again - and then the
 * counters really do double. Narrowing that window is why the whole batch goes in one
 * transaction; closing it would need an idempotency token per batch, which neither the design nor
 * the wire format has. A lost response costs one minute of inflated numbers on one route.
 *
 * MySQL only, like the rest of the bundle (see the MySQL DQL functions in `doctrine.dql`). The
 * column names come from the mapping rather than from string literals, because the application
 * owns the naming strategy.
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

	private ?string $baseSql = null;

	public function __construct(private readonly EntityManagerInterface $em)
	{
	}

	/**
	 * @param iterable<PerfBucket> $buckets rows to add into the table, in any order
	 * @return int how many rows were written
	 */
	public function upsert(iterable $buckets): int
	{
		$connection = $this->em->getConnection();
		$this->assertMysql($connection);

		return $connection->transactional(function (Connection $connection) use ($buckets): int {
			$written = 0;
			foreach ($buckets as $bucket) {
				[$sql, $params, $types] = $this->statementFor($bucket);
				$connection->executeStatement($sql, $params, $types);
				$written++;
			}

			return $written;
		});
	}

	/** @return array{string, list<mixed>, array<int, string>} */
	private function statementFor(PerfBucket $bucket): array
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

		foreach ([...self::ADDITIVE, ...self::GREATEST] as $field) {
			$params[] = $this->valueOf($bucket, $field);
		}

		$params[] = $bucket->getDurationHistogram();
		$types[count($params) - 1] = Types::JSON;

		$params[] = $bucket->getExtra();
		$types[count($params) - 1] = Types::JSON;

		// The same values again, for the UPDATE half of the statement. Writing them twice rather
		// than using VALUES() keeps the statement off a MySQL extension that 8.0.20 deprecated.
		foreach ([...self::ADDITIVE, ...self::GREATEST] as $field) {
			$params[] = $this->valueOf($bucket, $field);
		}
		foreach ($bucket->getDurationHistogram() as $count) {
			$params[] = $count;
		}

		$sql = $this->baseSql ??= $this->buildBaseSql();

		foreach ($this->extraOf($bucket) as $value) {
			$params[] = $value;
		}
		$sql .= $this->extraClause($bucket);

		return [$sql, $params, $types];
	}

	private function buildBaseSql(): string
	{
		$metadata = $this->em->getClassMetadata(PerfBucket::class);
		$table    = $metadata->getTableName();
		$project  = $metadata->getSingleAssociationJoinColumnName('project');

		$insert = [
			$this->column($metadata, 'granularity'),
			$this->column($metadata, 'bucketAt'),
			$project,
			$this->column($metadata, 'serverName'),
			$this->column($metadata, 'host'),
			$this->column($metadata, 'pathHash'),
			$this->column($metadata, 'path'),
		];
		foreach ([...self::ADDITIVE, ...self::GREATEST] as $field) {
			$insert[] = $this->column($metadata, $field);
		}
		$insert[] = $this->column($metadata, 'durationHistogram');
		$insert[] = $this->column($metadata, 'extra');

		$update = [];
		foreach (self::ADDITIVE as $field) {
			$column   = $this->column($metadata, $field);
			$update[] = sprintf('%1$s = %1$s + ?', $column);
		}
		foreach (self::GREATEST as $field) {
			$column   = $this->column($metadata, $field);
			$update[] = sprintf('%1$s = GREATEST(%1$s, ?)', $column);
		}
		$update[] = $this->histogramClause($this->column($metadata, 'durationHistogram'));

		// `path` is deliberately not updated: the key holds its hash, so an existing row already
		// has the path this one would write.
		return sprintf(
			'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
			$table,
			implode(', ', $insert),
			implode(', ', array_fill(0, count($insert), '?')),
			implode(', ', $update),
		);
	}

	/**
	 * Bin-by-bin addition in SQL. The cast is not decoration: MySQL does JSON arithmetic in
	 * DOUBLE, so without it a merged histogram comes back as `[2.0, 5.0, ...]` and every reader
	 * that expects counts breaks. COALESCE covers a row whose column somehow holds fewer bins,
	 * which would otherwise turn the whole array into NULL.
	 */
	private function histogramClause(string $column): string
	{
		$bins = [];
		for ($bin = 0; $bin < HistogramBins::COUNT; $bin++) {
			$bins[] = sprintf(
				"CAST(COALESCE(JSON_EXTRACT(%s, '$[%d]'), 0) + ? AS UNSIGNED)",
				$column,
				$bin,
			);
		}

		return sprintf('%s = JSON_ARRAY(%s)', $column, implode(', ', $bins));
	}

	/**
	 * Extra metrics are whatever the monitored application merged into `$GLOBALS['_bcperf_extra']`,
	 * so the keys are not known until the row arrives and the clause is built per row. They are
	 * also the only client-controlled identifier that reaches the SQL, hence the pattern.
	 */
	private function extraClause(PerfBucket $bucket): string
	{
		$extra = $this->extraOf($bucket);
		if ($extra === []) {
			return '';
		}

		$metadata = $this->em->getClassMetadata(PerfBucket::class);
		$column   = $this->column($metadata, 'extra');

		$pairs = [];
		foreach (array_keys($extra) as $name) {
			$path    = sprintf('$."%s"', $name);
			$pairs[] = sprintf(
				"'%s', COALESCE(JSON_EXTRACT(%s, '%s'), 0) + ?",
				$path,
				$column,
				$path,
			);
		}

		return sprintf(
			', %s = JSON_SET(COALESCE(%s, JSON_OBJECT()), %s)',
			$column,
			$column,
			implode(', ', $pairs),
		);
	}

	/** @return array<string, int|float> */
	private function extraOf(PerfBucket $bucket): array
	{
		$extra = $bucket->getExtra() ?? [];

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
				'%s needs MySQL: it upserts with ON DUPLICATE KEY UPDATE and merges histograms with JSON_ARRAY.',
				self::class,
			));
		}
	}
}
