<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Rollup;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Rollup\BucketMerger;
use BugCatcher\Service\Perf\Rollup\PathCapEnforcer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class PathCapEnforcerTest extends TestCase
{
	private Project $project;

	protected function setUp(): void
	{
		$this->project = new Project();
	}

	public function testBelowTheCapEveryPathKeepsItsOwnRow(): void
	{
		$capped = $this->enforcer(3)->enforce([
			$this->bucket('/a', 5),
			$this->bucket('/b', 4),
		]);

		$this->assertSame(['/a', '/b'], $this->paths($capped->buckets));
		$this->assertSame(0, $capped->foldedPaths);
	}

	/**
	 * The cap is a backstop for a pattern `PathNormalizer` failed to cover, so what survives is
	 * the traffic worth looking at - the busiest paths - and the tail becomes one row that says
	 * how much was left out.
	 */
	public function testTheTailBeyondTheCapBecomesOneRow(): void
	{
		$capped = $this->enforcer(2)->enforce([
			$this->bucket('/busy', 50),
			$this->bucket('/user/1', 3),
			$this->bucket('/user/2', 2),
			$this->bucket('/next', 20),
		]);

		$this->assertSame(['/busy', '/next', PerfBucket::OTHER_PATH], $this->paths($capped->buckets));
		$this->assertSame(2, $capped->foldedPaths);

		$other = $capped->buckets[2];
		$this->assertSame(5, $other->getHits());
		$this->assertSame('web-01', $other->getServerName());
		$this->assertSame('www.site.com', $other->getHost());
		$this->assertSame(PerfGranularity::Hour, $other->getGranularity());
	}

	/**
	 * Hour rows already hold an `__other__` of their own once minutes have been capped, and the
	 * day row has to put the two together rather than open a second fold. It is also not one of
	 * the paths the cap counts: it is the overflow, not traffic.
	 */
	public function testAnExistingOtherRowAbsorbsTheNewTailAndNeverCountsAgainstTheCap(): void
	{
		$capped = $this->enforcer(2)->enforce([
			$this->bucket(PerfBucket::OTHER_PATH, 7),
			$this->bucket('/busy', 50),
			$this->bucket('/next', 20),
			$this->bucket('/user/1', 3),
		]);

		$this->assertSame(['/busy', '/next', PerfBucket::OTHER_PATH], $this->paths($capped->buckets));
		$this->assertSame(1, $capped->foldedPaths);
		$this->assertSame(10, $capped->buckets[2]->getHits());
	}

	public function testAnOtherRowOnItsOwnIsLeftAlone(): void
	{
		$capped = $this->enforcer(2)->enforce([
			$this->bucket(PerfBucket::OTHER_PATH, 7),
			$this->bucket('/busy', 50),
		]);

		$this->assertSame(['/busy', PerfBucket::OTHER_PATH], $this->paths($capped->buckets));
		$this->assertSame(0, $capped->foldedPaths);
	}

	/** Two rows with the same traffic must not fold differently from one run to the next. */
	public function testPathsWithTheSameTrafficAreRankedByName(): void
	{
		$capped = $this->enforcer(1)->enforce([
			$this->bucket('/b', 5),
			$this->bucket('/a', 5),
		]);

		$this->assertSame(['/a', PerfBucket::OTHER_PATH], $this->paths($capped->buckets));
	}

	/**
	 * `server_name` and `host` are part of the key, so a fold across them would be a row claiming
	 * measurements from a machine it does not name. Each machine and vhost gets its own cap.
	 */
	public function testEachMachineAndVhostIsCappedOnItsOwn(): void
	{
		$capped = $this->enforcer(1)->enforce([
			$this->bucket('/a', 5),
			$this->bucket('/b', 4),
			$this->bucket('/a', 5, serverName: 'web-02'),
			$this->bucket('/b', 4, serverName: 'web-02'),
			$this->bucket('/a', 5, host: 'api.site.com'),
			$this->bucket('/b', 4, host: 'api.site.com'),
		]);

		$this->assertCount(6, $capped->buckets);
		$this->assertSame(3, $capped->foldedPaths);

		$others = array_values(array_filter(
			$capped->buckets,
			static fn(PerfBucket $bucket): bool => $bucket->getPath() === PerfBucket::OTHER_PATH,
		));
		$this->assertCount(3, $others);
		foreach ($others as $other) {
			$this->assertSame(4, $other->getHits());
		}
	}

	public function testThereIsNothingToCapInAnEmptyBucket(): void
	{
		$capped = $this->enforcer(2)->enforce([]);

		$this->assertSame([], $capped->buckets);
		$this->assertSame(0, $capped->foldedPaths);
	}

	private function enforcer(int $cap): PathCapEnforcer
	{
		return new PathCapEnforcer(new BucketMerger(), $cap);
	}

	/**
	 * @param list<PerfBucket> $buckets
	 * @return list<string>
	 */
	private function paths(array $buckets): array
	{
		return array_map(static fn(PerfBucket $bucket): string => $bucket->getPath(), $buckets);
	}

	private function bucket(
		string $path,
		int $hits,
		string $serverName = 'web-01',
		string $host = 'www.site.com',
	): PerfBucket {
		return new PerfBucket(
			PerfGranularity::Hour,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			$this->project,
			$serverName,
			$host,
			$path,
			hits: $hits,
		);
	}
}
