<?php

declare(strict_types=1);

namespace BugCatcher\Repository;

use BugCatcher\Entity\DurationHistogram;
use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\PerfBucketExtra;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\PerfWindow;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * Reading `perf_bucket`. Every report and dashboard query belongs here rather than in the
 * component that draws it — the alternative is SQL spread across Twig components, which is what
 * `LogSparkLine` does today and is not worth repeating.
 *
 * Writing is {@see \BugCatcher\Service\Perf\Ingest\PerfBucketUpserter}, which goes through DBAL:
 * `INSERT ... ON DUPLICATE KEY UPDATE` is what makes a retried batch converge, and the ORM has no
 * way to express it.
 *
 * @extends ServiceEntityRepository<PerfBucket>
 *
 * @method PerfBucket|null find($id, $lockMode = null, $lockVersion = null)
 * @method PerfBucket|null findOneBy(array $criteria, array $orderBy = null)
 * @method PerfBucket[]    findAll()
 * @method PerfBucket[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class PerfBucketRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, PerfBucket::class);
	}

	/**
	 * The unique key, spelled once. `$bucketAt` is floored to the granularity, so a caller may
	 * hand in any instant inside the bucket it means.
	 */
	public function findOneByKey(
		PerfGranularity $granularity,
		DateTimeImmutable $bucketAt,
		Project $project,
		string $serverName,
		string $host,
		string $path,
	): ?PerfBucket {
		return $this->createQueryBuilder('b')
			->andWhere('b.granularity = :granularity')
			->andWhere('b.bucketAt = :bucketAt')
			->andWhere('b.project = :project')
			->andWhere('b.serverName = :serverName')
			->andWhere('b.host = :host')
			->andWhere('b.pathHash = :pathHash')
			->setParameter('granularity', $granularity->value)
			->setParameter('bucketAt', $granularity->floor($bucketAt))
			->setParameter('project', $project->getId(), UuidType::NAME)
			->setParameter('serverName', $serverName)
			->setParameter('host', $host)
			->setParameter('pathHash', PerfBucket::hashPath($path))
			->getQuery()
			->getOneOrNullResult();
	}

	/**
	 * How many buckets of this granularity are older than the instant - what `app:perf:purge
	 * --dry-run` reports instead of deleting them.
	 */
	public function countOlderThan(PerfGranularity $granularity, DateTimeImmutable $cutoff): int
	{
		return (int)$this->createQueryBuilder('b')
			->select('COUNT(b.id)')
			->andWhere('b.granularity = :granularity')
			->andWhere('b.bucketAt < :cutoff')
			->setParameter('granularity', $granularity->value)
			->setParameter('cutoff', $cutoff)
			->getQuery()
			->getSingleScalarResult();
	}

	/**
	 * The projects that measured anything of this granularity in `[$from, $to)`.
	 *
	 * The roll-up asks before it works: a project that shipped nothing has no buckets to compute,
	 * and nothing in the configuration says which projects a collector is installed for.
	 *
	 * @return list<Project>
	 */
	public function projectsWithBuckets(
		PerfGranularity $granularity,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
	): array {
		return $this->getEntityManager()->createQueryBuilder()
			->select('p')
			->distinct()
			->from(Project::class, 'p')
			->join(PerfBucket::class, 'b', Join::WITH, 'b.project = p')
			->andWhere('b.granularity = :granularity')
			->andWhere('b.bucketAt >= :from')
			->andWhere('b.bucketAt < :to')
			->setParameter('granularity', $granularity->value)
			->setParameter('from', $from)
			->setParameter('to', $to)
			->getQuery()
			->getResult();
	}

	/**
	 * The boundaries of the target buckets that actually have source rows under them, oldest
	 * first.
	 *
	 * Walking every boundary in the window instead would mean one aggregation per empty hour,
	 * which for a `--from` three months back is thousands of queries that count nothing.
	 *
	 * @return list<DateTimeImmutable>
	 */
	public function boundariesToRollUp(PerfWindow $window, Project $project): array
	{
		$target = $window->granularity;
		$source = $target->finer() ?? throw new InvalidArgumentException(
			sprintf('Nothing rolls up into %s.', $target->value),
		);

		$timestamps = $this->createQueryBuilder('b')
			->select('b.bucketAt')
			->distinct()
			->andWhere('b.granularity = :granularity')
			->andWhere('b.project = :project')
			->andWhere('b.bucketAt >= :from')
			->andWhere('b.bucketAt < :to')
			->orderBy('b.bucketAt', 'ASC')
			->setParameter('granularity', $source->value)
			->setParameter('project', $project->getId(), UuidType::NAME)
			->setParameter('from', $window->from)
			->setParameter('to', $window->to)
			->getQuery()
			->getSingleColumnResult();

		// scalar hydration hands back whatever the driver read, so the column is converted here
		// rather than assumed
		$type     = Type::getType(Types::DATETIME_IMMUTABLE);
		$platform = $this->connection()->getDatabasePlatform();

		$boundaries = [];
		foreach ($timestamps as $timestamp) {
			$boundary = $target->floor($type->convertToPHPValue($timestamp, $platform));

			$boundaries[$boundary->format('Y-m-d H:i:s')] = $boundary;
		}

		return array_values($boundaries);
	}

	/**
	 * One target bucket, as the rows it is made of.
	 *
	 * The cascade is exact - counts and sums add, maxima take the greatest, histogram bins add one
	 * by one - and it is done in SQL, where the source rows already are. What comes back is a row
	 * per route per machine per vhost, carrying the target's granularity and boundary, ready for
	 * {@see \BugCatcher\Service\Perf\Rollup\PathCapEnforcer} and the upserter. Nothing here is
	 * managed by the ORM: these are values on their way back into the table.
	 *
	 * @param DateTimeImmutable $bucketAt any instant inside the target bucket
	 * @return list<PerfBucket>
	 */
	public function aggregateInto(PerfGranularity $target, DateTimeImmutable $bucketAt, Project $project): array
	{
		$source = $target->finer() ?? throw new InvalidArgumentException(
			sprintf('Nothing rolls up into %s.', $target->value),
		);

		$from = $target->floor($bucketAt);
		$to   = $from->add($target->interval());

		$extra = $this->aggregateExtra($source, $project, $from, $to);
		$rows  = [];

		foreach ($this->aggregateRows($source, $project, $from, $to) as $row) {
			$histogram = [];
			for ($bin = 0; $bin < HistogramBins::COUNT; $bin++) {
				$histogram[] = (int)$row['bin' . $bin];
			}

			$rows[] = new PerfBucket(
				$target,
				$from,
				$project,
				(string)$row['serverName'],
				(string)$row['host'],
				(string)$row['path'],
				hits: (int)$row['hits'],
				sumDuration: (float)$row['sumDuration'],
				sumUser: (float)$row['sumUser'],
				sumSys: (float)$row['sumSys'],
				maxDuration: (float)$row['maxDuration'],
				sumMem: (int)$row['sumMem'],
				maxMem: (int)$row['maxMem'],
				clientErrors: (int)$row['clientErrors'],
				serverErrors: (int)$row['serverErrors'],
				durationHistogram: $histogram,
				extra: $extra[$this->rowKey($row)] ?? [],
			);
		}

		return $rows;
	}

	/**
	 * Measurements are read through DBAL rather than DQL: this is an aggregate of sixteen bins and
	 * nine counters that has no business being hydrated into entities first. Column names come
	 * from the mapping, because the naming strategy belongs to the application - the same reason
	 * {@see \BugCatcher\Service\Perf\Ingest\PerfBucketUpserter} builds its statement that way.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function aggregateRows(
		PerfGranularity $source,
		Project $project,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
	): array {
		$metadata = $this->bucketMetadata();
		$table    = $metadata->getTableName();
		// every column is aliased to the property it belongs to, so what comes back is read by
		// the same names the entity is built with
		$select   = [
			$this->aliased($metadata, 'serverName'),
			$this->aliased($metadata, 'host'),
			$this->aliased($metadata, 'pathHash'),
			// one hash is one path, so any of them is the path
			sprintf('MIN(%s) AS path', $metadata->getColumnName('path')),
		];

		foreach (['hits', 'sumDuration', 'sumUser', 'sumSys', 'sumMem', 'clientErrors', 'serverErrors'] as $field) {
			$select[] = sprintf('SUM(%s) AS %s', $metadata->getColumnName($field), $field);
		}
		foreach (['maxDuration', 'maxMem'] as $field) {
			$select[] = sprintf('MAX(%s) AS %s', $metadata->getColumnName($field), $field);
		}
		for ($bin = 0; $bin < HistogramBins::COUNT; $bin++) {
			$column   = $metadata->getColumnName('durationHistogram.' . DurationHistogram::fieldFor($bin));
			$select[] = sprintf('SUM(%s) AS bin%d', $column, $bin);
		}

		$sql = sprintf(
			'SELECT %s FROM %s WHERE %s = ? AND %s = ? AND %s >= ? AND %s < ? GROUP BY %s, %s, %s',
			implode(', ', $select),
			$table,
			$metadata->getColumnName('granularity'),
			$metadata->getSingleAssociationJoinColumnName('project'),
			$metadata->getColumnName('bucketAt'),
			$metadata->getColumnName('bucketAt'),
			$metadata->getColumnName('serverName'),
			$metadata->getColumnName('host'),
			$metadata->getColumnName('pathHash'),
		);

		return $this->connection()->fetchAllAssociative(
			$sql,
			[$source->value, $project->getId(), $from, $to],
			[1 => UuidType::NAME, 2 => Types::DATETIME_IMMUTABLE, 3 => Types::DATETIME_IMMUTABLE],
		);
	}

	/**
	 * The extra metrics of the same window, summed per row and keyed the way the rows are.
	 *
	 * @return array<string, array<string, float>>
	 */
	private function aggregateExtra(
		PerfGranularity $source,
		Project $project,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
	): array {
		$bucket = $this->bucketMetadata();
		$metric = $this->getEntityManager()->getClassMetadata(PerfBucketExtra::class);

		$sql = sprintf(
			'SELECT b.%s AS serverName, b.%s AS host, b.%s AS pathHash, e.%s AS name, SUM(e.%s) AS value'
			. ' FROM %s e INNER JOIN %s b ON b.%s = e.%s'
			. ' WHERE b.%s = ? AND b.%s = ? AND b.%s >= ? AND b.%s < ?'
			. ' GROUP BY b.%s, b.%s, b.%s, e.%s',
			$bucket->getColumnName('serverName'),
			$bucket->getColumnName('host'),
			$bucket->getColumnName('pathHash'),
			$metric->getColumnName('name'),
			$metric->getColumnName('value'),
			$metric->getTableName(),
			$bucket->getTableName(),
			$bucket->getColumnName('id'),
			$metric->getSingleAssociationJoinColumnName('bucket'),
			$bucket->getColumnName('granularity'),
			$bucket->getSingleAssociationJoinColumnName('project'),
			$bucket->getColumnName('bucketAt'),
			$bucket->getColumnName('bucketAt'),
			$bucket->getColumnName('serverName'),
			$bucket->getColumnName('host'),
			$bucket->getColumnName('pathHash'),
			$metric->getColumnName('name'),
		);

		$extra = [];
		$rows  = $this->connection()->fetchAllAssociative(
			$sql,
			[$source->value, $project->getId(), $from, $to],
			[1 => UuidType::NAME, 2 => Types::DATETIME_IMMUTABLE, 3 => Types::DATETIME_IMMUTABLE],
		);

		foreach ($rows as $row) {
			$extra[$this->rowKey($row)][(string)$row['name']] = (float)$row['value'];
		}

		return $extra;
	}

	/** What tells one aggregated row from another, for both of the queries above. */
	private function rowKey(array $row): string
	{
		// \x1f rather than a printable separator: a vhost is whatever the request said it was
		return $row['serverName'] . "\x1f" . $row['host'] . "\x1f" . $row['pathHash'];
	}

	private function aliased(ClassMetadata $metadata, string $field): string
	{
		return sprintf('%s AS %s', $metadata->getColumnName($field), $field);
	}

	/** @return ClassMetadata<PerfBucket> */
	private function bucketMetadata(): ClassMetadata
	{
		return $this->getEntityManager()->getClassMetadata(PerfBucket::class);
	}

	private function connection(): Connection
	{
		return $this->getEntityManager()->getConnection();
	}
}
