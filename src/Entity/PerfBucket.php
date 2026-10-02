<?php

declare(strict_types=1);

namespace BugCatcher\Entity;

use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Histogram\HistogramMath;
use DateTimeImmutable;

/**
 * What one route cost on one machine over one minute, hour or day.
 *
 * Deliberately **not** a {@see Record}. A request that took 40 ms is not an event anybody
 * resolves, and the volumes differ by three orders of magnitude: `Record` uses JOINED inheritance
 * with a UUID primary key and five indexes, so a row per HTTP request would mean two INSERTs and a
 * random UUID into a B-tree on a table sized for hundreds of errors a day. This one has a table of
 * its own and a `BIGINT AUTO_INCREMENT` key, so inserts append to the index instead of scattering
 * through it.
 *
 * Durations are **seconds** and memory is **bytes**, the units the collector puts on the wire;
 * milliseconds appear no earlier than the report DTOs. `serverName` is the machine and `host` is
 * the vhost, kept apart because "checkout is slow" and "checkout is slow on web-03" are different
 * sentences and only the second one says where to look.
 *
 * Rows are written by `Service\Perf\Ingest\PerfBucketUpserter` through DBAL, never through the
 * ORM, so there are no setters: `INSERT ... ON DUPLICATE KEY UPDATE` is what makes a retried batch
 * converge instead of double. The constructor is for fixtures and for reading, and it enforces the
 * two invariants that the unique key depends on — the timestamp sits on a bucket boundary and
 * `pathHash` is the hash of `path`.
 */
final class PerfBucket
{
	/** Where the roll-up folds the tail beyond `perf.rollup_path_cap` distinct paths. */
	public const string OTHER_PATH = '__other__';

	/**
	 * What an {@see self::getExtra()} metric may be called. Names come from the monitored
	 * application and end up in a JSON path in the upsert statement, so the pattern is enforced
	 * at the API edge as a 422 and again in the upserter as a last line of defence.
	 */
	public const string EXTRA_NAME_PATTERN = '/^[A-Za-z0-9_][A-Za-z0-9_.\-]{0,31}$/';

	private ?int $id = null;

	private string $pathHash;

	/** @var list<int> */
	private array $durationHistogram;

	/** @param list<int>|null $durationHistogram @param array<string, int|float>|null $extra */
	public function __construct(
		private PerfGranularity $granularity,
		private DateTimeImmutable $bucketAt,
		private Project $project,
		private string $serverName,
		private string $host,
		private string $path,
		private int $hits = 0,
		private float $sumDuration = 0.0,
		private float $sumUser = 0.0,
		private float $sumSys = 0.0,
		private float $maxDuration = 0.0,
		private int $sumMem = 0,
		private int $maxMem = 0,
		private int $clientErrors = 0,
		private int $serverErrors = 0,
		?array $durationHistogram = null,
		private ?array $extra = null,
	) {
		$this->bucketAt          = $granularity->floor($bucketAt);
		$this->pathHash          = self::hashPath($path);
		$this->durationHistogram = $durationHistogram === null
			? HistogramBins::empty()
			: HistogramMath::normalise($durationHistogram);
		$this->extra             = $extra === [] ? null : $extra;
	}

	/**
	 * The unique key holds this rather than the path itself: a normalised path still runs to a
	 * kilobyte, which no index wants.
	 */
	public static function hashPath(string $path): string
	{
		return md5($path);
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getGranularity(): PerfGranularity
	{
		return $this->granularity;
	}

	public function getBucketAt(): DateTimeImmutable
	{
		return $this->bucketAt;
	}

	public function getProject(): Project
	{
		return $this->project;
	}

	public function getServerName(): string
	{
		return $this->serverName;
	}

	public function getHost(): string
	{
		return $this->host;
	}

	public function getPath(): string
	{
		return $this->path;
	}

	public function getPathHash(): string
	{
		return $this->pathHash;
	}

	public function getHits(): int
	{
		return $this->hits;
	}

	/** Seconds. */
	public function getSumDuration(): float
	{
		return $this->sumDuration;
	}

	/** Seconds of user CPU. */
	public function getSumUser(): float
	{
		return $this->sumUser;
	}

	/** Seconds of system CPU. */
	public function getSumSys(): float
	{
		return $this->sumSys;
	}

	/** Seconds. The slowest single request in the bucket, never scaled by the sample rate. */
	public function getMaxDuration(): float
	{
		return $this->maxDuration;
	}

	/** Bytes of peak memory, summed over the hits. */
	public function getSumMem(): int
	{
		return $this->sumMem;
	}

	/** Bytes. The highest peak of a single request in the bucket. */
	public function getMaxMem(): int
	{
		return $this->maxMem;
	}

	public function getClientErrors(): int
	{
		return $this->clientErrors;
	}

	public function getServerErrors(): int
	{
		return $this->serverErrors;
	}

	/** @return list<int> counts per bin of {@see HistogramBins} */
	public function getDurationHistogram(): array
	{
		return $this->durationHistogram;
	}

	/**
	 * Whatever the monitored application merged into `$GLOBALS['_bcperf_extra']` — SQL query count
	 * and time for a Doctrine application, anything numeric for anyone else.
	 *
	 * @return array<string, int|float>|null
	 */
	public function getExtra(): ?array
	{
		return $this->extra;
	}
}
