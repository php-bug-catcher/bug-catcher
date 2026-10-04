<?php

declare(strict_types=1);

namespace BugCatcher\Repository;

use BugCatcher\Entity\DurationHistogram;
use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\PerfBucketExtra;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Enum\PerfTopPathGroup;
use BugCatcher\Service\Perf\WindowAggregate;
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
	/** One row of a bucket: a route on a machine on a vhost. */
	private const array PER_BUCKET_ROW = ['serverName', 'host', 'pathHash'];

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

		$extra = $this->aggregateExtra(self::PER_BUCKET_ROW, $source, $project, $from, $to);
		$rows  = [];

		foreach ($this->aggregateRows(self::PER_BUCKET_ROW, $source, $project, $from, $to) as $row) {
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
				durationHistogram: $this->histogramOf($row),
				extra: $extra[$this->rowKey(self::PER_BUCKET_ROW, $row)] ?? [],
			);
		}

		return $rows;
	}

	/**
	 * What every route did over a window, one entry per route.
	 *
	 * Machines and vhosts are folded together here, unlike in {@see aggregateInto()}: this feeds
	 * detection, and "checkout got slower" is a statement about the route. A regression that
	 * shows on one machine out of three still shows in the total.
	 *
	 * @param DateTimeImmutable $to exclusive
	 * @param string|null $pathHash one route rather than all of them
	 * @return array<string, WindowAggregate> keyed by path hash
	 */
	public function aggregateByPath(
		PerfGranularity $granularity,
		Project $project,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
		?string $pathHash = null,
	): array {
		return $this->aggregate('pathHash', 'path', $granularity, $project, $from, $to, $pathHash);
	}

	/**
	 * The same window sliced by whatever the table on the page is grouped by.
	 *
	 * Ordered by traffic and limited in SQL, because the number of distinct routes in a window is
	 * whatever the monitored application produced and the page shows a screenful. A consequence
	 * worth knowing: a table sorted by p95 is the slowest **of the busiest** rows, since the
	 * percentile is estimated from the bins after they have been summed.
	 *
	 * @return array<string, WindowAggregate>
	 */
	public function aggregateGrouped(
		PerfTopPathGroup $group,
		PerfGranularity $granularity,
		?Project $project,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
		int $limit,
	): array {
		return $this->aggregate(
			$group->field(),
			$group === PerfTopPathGroup::Path ? 'path' : $group->field(),
			$granularity,
			$project,
			$from,
			$to,
			limit: $limit,
			// across projects every row has to say which application it belongs to: two of them
			// both have a `/login`, and one merged row would be a row about nothing
			splitByProject: $project === null,
		);
	}

	/**
	 * One entry per bucket of the window, in order, for the charts.
	 *
	 * Buckets nothing happened in are simply absent here; filling the gaps belongs to
	 * {@see \BugCatcher\Service\Perf\Report\PerfReportBuilder}, which knows which boundaries
	 * the window has.
	 *
	 * @return array<string, WindowAggregate> keyed by `Y-m-d H:i:s` of the bucket
	 */
	public function aggregateByBucket(
		PerfGranularity $granularity,
		?Project $project,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
		?string $pathHash = null,
	): array {
		$type       = Type::getType(Types::DATETIME_IMMUTABLE);
		$platform   = $this->connection()->getDatabasePlatform();
		$aggregates = [];

		foreach ($this->aggregate('bucketAt', 'bucketAt', $granularity, $project, $from, $to, $pathHash) as $slice) {
			$at = $type->convertToPHPValue($slice->label, $platform)->format('Y-m-d H:i:s');

			$aggregates[$at] = new WindowAggregate(
				$at,
				$at,
				$slice->hits,
				$slice->sumDuration,
				$slice->sumUser,
				$slice->sumSys,
				$slice->maxDuration,
				$slice->sumMem,
				$slice->maxMem,
				$slice->clientErrors,
				$slice->serverErrors,
				$slice->durationHistogram,
				$slice->extra,
			);
		}

		ksort($aggregates);

		return $aggregates;
	}

	/**
	 * The whole window as one row: no grouping at all.
	 *
	 * What a dashboard row asks for - a project's p95 and Apdex over the last hour are statements
	 * about the window, not about each of its sixty minutes. Summing the per-bucket aggregates in
	 * PHP would work for the counters and be wrong for the percentile: a percentile of percentiles
	 * is not a percentile. Here the sixteen bins are added in SQL and the estimate is taken once,
	 * off the total.
	 */
	public function aggregateWindow(
		PerfGranularity $granularity,
		?Project $project,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
	): WindowAggregate {
		$rows = $this->aggregateRows([], $granularity, $project, $from, $to);
		$row  = $rows[0] ?? null;

		// an aggregate over no rows is one row of nulls, which is a window with no traffic
		if ($row === null || $row['hits'] === null) {
			return new WindowAggregate('', '', 0, 0.0, 0.0, 0.0, 0.0, 0, 0, 0, 0, HistogramBins::empty());
		}

		return new WindowAggregate(
			'',
			'',
			(int)$row['hits'],
			(float)$row['sumDuration'],
			(float)$row['sumUser'],
			(float)$row['sumSys'],
			(float)$row['maxDuration'],
			(int)$row['sumMem'],
			(int)$row['maxMem'],
			(int)$row['clientErrors'],
			(int)$row['serverErrors'],
			$this->histogramOf($row),
			$this->aggregateExtra([], $granularity, $project, $from, $to)[''] ?? [],
		);
	}

	/**
	 * @param string $keyField what identifies a slice
	 * @param string $labelField what a slice is called
	 * @return array<string, WindowAggregate>
	 */
	private function aggregate(
		string $keyField,
		string $labelField,
		PerfGranularity $granularity,
		?Project $project,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
		?string $pathHash = null,
		?int $limit = null,
		bool $splitByProject = false,
	): array {
		$groupBy = [$keyField];
		// the extras have to be sliced the same way the measurements are, project included:
		// grouped only by the key field, every application's `/login` would be handed the sum of
		// all of them
		$extra      = $this->aggregateExtra($groupBy, $granularity, $project, $from, $to, $pathHash, $splitByProject);
		$aggregates = [];

		foreach ($this->aggregateRows($groupBy, $granularity, $project, $from, $to, $pathHash, $limit, $splitByProject) as $row) {
			$code = $splitByProject ? (string)$row['projectCode'] : '';
			$key  = $code === '' ? (string)$row[$keyField] : $code . "\x1f" . $row[$keyField];

			$aggregates[$key] = new WindowAggregate(
				$code === '' ? (string)$row[$labelField] : $code . ' ' . $row[$labelField],
				$key,
				(int)$row['hits'],
				(float)$row['sumDuration'],
				(float)$row['sumUser'],
				(float)$row['sumSys'],
				(float)$row['maxDuration'],
				(int)$row['sumMem'],
				(int)$row['maxMem'],
				(int)$row['clientErrors'],
				(int)$row['serverErrors'],
				$this->histogramOf($row),
				$extra[$key] ?? [],
			);
		}

		return $aggregates;
	}

	/**
	 * Measurements are read through DBAL rather than DQL: this is an aggregate of sixteen bins and
	 * nine counters that has no business being hydrated into entities first. Column names come
	 * from the mapping, because the naming strategy belongs to the application - the same reason
	 * {@see \BugCatcher\Service\Perf\Ingest\PerfBucketUpserter} builds its statement that way.
	 *
	 * @param list<string> $groupBy the fields that tell one result row from another. Empty is the
	 *     whole window as one row - aggregate functions with no GROUP BY return exactly one,
	 *     all-null when nothing matched
	 * @return list<array<string, mixed>>
	 */
	private function aggregateRows(
		array $groupBy,
		PerfGranularity $granularity,
		?Project $project,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
		?string $pathHash = null,
		?int $limit = null,
		bool $splitByProject = false,
	): array {
		$metadata = $this->bucketMetadata();

		// every column is aliased to the property it belongs to, so what comes back is read by
		// the same names the entity is built with
		$select  = array_map(fn(string $field): string => 'b.' . $this->aliased($metadata, $field), $groupBy);
		$grouped = array_map(fn(string $field): string => 'b.' . $metadata->getColumnName($field), $groupBy);
		// one hash is one path, so any of them is the path
		$select[] = sprintf('MIN(b.%s) AS path', $metadata->getColumnName('path'));

		foreach (['hits', 'sumDuration', 'sumUser', 'sumSys', 'sumMem', 'clientErrors', 'serverErrors'] as $field) {
			$select[] = sprintf('SUM(b.%s) AS %s', $metadata->getColumnName($field), $field);
		}
		foreach (['maxDuration', 'maxMem'] as $field) {
			$select[] = sprintf('MAX(b.%s) AS %s', $metadata->getColumnName($field), $field);
		}
		for ($bin = 0; $bin < HistogramBins::COUNT; $bin++) {
			$column   = $metadata->getColumnName('durationHistogram.' . DurationHistogram::fieldFor($bin));
			$select[] = sprintf('SUM(b.%s) AS bin%d', $column, $bin);
		}

		$params = [$granularity->value, $from, $to];
		$types  = [1 => Types::DATETIME_IMMUTABLE, 2 => Types::DATETIME_IMMUTABLE];
		$where  = '';
		$join   = '';

		if ($project !== null) {
			$where    = sprintf(' AND b.%s = ?', $metadata->getSingleAssociationJoinColumnName('project'));
			$params[] = $project->getId();
			$types[count($params) - 1] = UuidType::NAME;
		} elseif ($splitByProject) {
			// the code rather than the id: it is what a reader of the table recognises, and it
			// saves turning sixteen binary bytes back into a project
			$projects = $this->getEntityManager()->getClassMetadata(Project::class);
			$join     = sprintf(
				' INNER JOIN %s p ON p.%s = b.%s',
				$projects->getTableName(),
				$projects->getColumnName('id'),
				$metadata->getSingleAssociationJoinColumnName('project'),
			);
			$select[]  = sprintf('p.%s AS projectCode', $projects->getColumnName('code'));
			$grouped[] = sprintf('p.%s', $projects->getColumnName('code'));
		}

		if ($pathHash !== null) {
			$where    .= sprintf(' AND b.%s = ?', $metadata->getColumnName('pathHash'));
			$params[]  = $pathHash;
		}

		$sql = sprintf(
			'SELECT %s FROM %s b%s WHERE b.%s = ? AND b.%s >= ? AND b.%s < ?%s%s',
			implode(', ', $select),
			$metadata->getTableName(),
			$join,
			$metadata->getColumnName('granularity'),
			$metadata->getColumnName('bucketAt'),
			$metadata->getColumnName('bucketAt'),
			$where,
			$grouped === [] ? '' : ' GROUP BY ' . implode(', ', $grouped),
		);

		// busiest first, so that a limit keeps the rows worth looking at
		if ($limit !== null) {
			$sql .= sprintf(' ORDER BY SUM(b.%s) DESC LIMIT %d', $metadata->getColumnName('hits'), $limit);
		}

		return $this->connection()->fetchAllAssociative($sql, $params, $types);
	}

	/**
	 * The extra metrics of the same window, summed per result row and keyed the way those rows
	 * are. Whatever the monitored application put in `$GLOBALS['_bcperf_extra']` reaches a custom
	 * metric extractor - and the database panel - through here.
	 *
	 * A query of its own rather than a join onto {@see aggregateRows()}: `perf_bucket_extra` holds
	 * one row per metric per bucket, so joining it would multiply every count and every histogram
	 * bin by however many metrics the application happens to send.
	 *
	 * @param list<string> $groupBy empty is the whole window under the key `''`, the way
	 *     {@see aggregateRows()} reads it
	 * @param bool $splitByProject keep the applications apart, exactly as {@see aggregateRows()}
	 *     does - the keys of what comes back have to match the ones the rows are stored under
	 * @return array<string, array<string, float>>
	 */
	private function aggregateExtra(
		array $groupBy,
		PerfGranularity $granularity,
		?Project $project,
		DateTimeImmutable $from,
		DateTimeImmutable $to,
		?string $pathHash = null,
		bool $splitByProject = false,
	): array {
		$bucket = $this->bucketMetadata();
		$metric = $this->getEntityManager()->getClassMetadata(PerfBucketExtra::class);

		$keyColumns = array_map(fn(string $field): string => 'b.' . $bucket->getColumnName($field), $groupBy);
		$select     = array_map(
			fn(string $field): string => sprintf('b.%s AS %s', $bucket->getColumnName($field), $field),
			$groupBy,
		);

		$params = [$granularity->value, $from, $to];
		$types  = [1 => Types::DATETIME_IMMUTABLE, 2 => Types::DATETIME_IMMUTABLE];
		$route  = '';
		$join   = '';

		if ($project !== null) {
			$route    = sprintf(' AND b.%s = ?', $bucket->getSingleAssociationJoinColumnName('project'));
			$params[] = $project->getId();
			$types[count($params) - 1] = UuidType::NAME;
		} elseif ($splitByProject) {
			$projects = $this->getEntityManager()->getClassMetadata(Project::class);
			$join     = sprintf(
				' INNER JOIN %s p ON p.%s = b.%s',
				$projects->getTableName(),
				$projects->getColumnName('id'),
				$bucket->getSingleAssociationJoinColumnName('project'),
			);
			$select[]     = sprintf('p.%s AS projectCode', $projects->getColumnName('code'));
			$keyColumns[] = sprintf('p.%s', $projects->getColumnName('code'));
		}

		if ($pathHash !== null) {
			$route    .= sprintf(' AND b.%s = ?', $bucket->getColumnName('pathHash'));
			$params[]  = $pathHash;
		}

		// the metric name is always selected and always grouped by; the key columns come first
		// when there are any, and there are none when the whole window is one row
		$select[]     = sprintf('e.%s AS name', $metric->getColumnName('name'));
		$select[]     = sprintf('SUM(e.%s) AS value', $metric->getColumnName('value'));
		$keyColumns[] = sprintf('e.%s', $metric->getColumnName('name'));

		$sql = sprintf(
			'SELECT %s'
			. ' FROM %s e INNER JOIN %s b ON b.%s = e.%s%s'
			. ' WHERE b.%s = ? AND b.%s >= ? AND b.%s < ?%s'
			. ' GROUP BY %s',
			implode(', ', $select),
			$metric->getTableName(),
			$bucket->getTableName(),
			$bucket->getColumnName('id'),
			$metric->getSingleAssociationJoinColumnName('bucket'),
			$join,
			$bucket->getColumnName('granularity'),
			$bucket->getColumnName('bucketAt'),
			$bucket->getColumnName('bucketAt'),
			$route,
			implode(', ', $keyColumns),
		);

		$extra = [];
		$rows  = $this->connection()->fetchAllAssociative($sql, $params, $types);

		foreach ($rows as $row) {
			$key  = $this->rowKey($groupBy, $row);
			$code = $splitByProject ? (string)$row['projectCode'] : '';

			if ($code !== '') {
				$key = $code . "\x1f" . $key;
			}

			$extra[$key][(string)$row['name']] = (float)$row['value'];
		}

		return $extra;
	}

	/** @return list<int> */
	private function histogramOf(array $row): array
	{
		$histogram = [];
		for ($bin = 0; $bin < HistogramBins::COUNT; $bin++) {
			$histogram[] = (int)$row['bin' . $bin];
		}

		return $histogram;
	}

	/**
	 * What tells one aggregated row from another, for both of the queries above.
	 *
	 * @param non-empty-list<string> $groupBy
	 */
	private function rowKey(array $groupBy, array $row): string
	{
		// \x1f rather than a printable separator: a vhost is whatever the request said it was
		return implode("\x1f", array_map(static fn(string $field): string => (string)$row[$field], $groupBy));
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
