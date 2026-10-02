<?php

declare(strict_types=1);

namespace BugCatcher\Repository;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
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
}
