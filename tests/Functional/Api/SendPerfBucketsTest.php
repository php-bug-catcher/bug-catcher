<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Functional\Api;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Functional\apiTestHelper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class SendPerfBucketsTest extends KernelTestCase
{
	use apiTestHelper;

	public function testABatchOfBucketsIsStored(): void
	{
		[$browser] = $this->browser();
		ProjectFactory::createOne(['code' => 'testProject']);

		$browser
			->post('/api/perf_buckets', $this->payload([
				$this->row(path: '/user/{id}', hits: 5, sumDuration: 2.19),
				$this->row(path: '/feed/', hits: 2, sumDuration: 0.4),
			]))
			->assertStatus(204);

		$stored = $this->stored('/user/{id}');
		$this->assertNotNull($stored);
		$this->assertSame(5, $stored->getHits());
		$this->assertSame(2.19, $stored->getSumDuration());
		$this->assertSame('web-01', $stored->getServerName());
		$this->assertSame('www.site.com', $stored->getHost());
		$this->assertSame('2026-03-10 14:37:00', $stored->getBucketAt()->format('Y-m-d H:i:s'));
		$this->assertNotNull($this->stored('/feed/'));
	}

	/**
	 * What the collector ships is always a minute. Letting a client name the granularity would let
	 * it write rows the roll-up believes it computed itself.
	 */
	public function testEveryRowLandsAsAMinuteWhateverTheClientSays(): void
	{
		[$browser] = $this->browser();
		ProjectFactory::createOne(['code' => 'testProject']);

		$browser
			->post('/api/perf_buckets', $this->payload([
				['granularity' => 'day'] + $this->row(),
			]))
			->assertStatus(204);

		$this->assertNotNull($this->stored(granularity: PerfGranularity::Minute));
		$this->assertNull($this->stored(granularity: PerfGranularity::Day));
	}

	public function testAnUnknownProjectIsNotFound(): void
	{
		[$browser] = $this->browser();

		$browser
			->post('/api/perf_buckets', $this->payload([$this->row()], 'noSuchProject'))
			->assertStatus(404);
	}

	public function testASecondBatchForTheSameMinuteAddsToIt(): void
	{
		[$browser] = $this->browser();
		ProjectFactory::createOne(['code' => 'testProject']);

		$browser->post('/api/perf_buckets', $this->payload([$this->row(hits: 5)]))->assertStatus(204);
		$browser->post('/api/perf_buckets', $this->payload([$this->row(hits: 3)]))->assertStatus(204);

		$this->assertSame(8, $this->stored()->getHits());
	}

	/**
	 * @dataProvider invalidPayloads
	 */
	public function testAPayloadThatIsNotAMeasurementIsRejected(array $payload): void
	{
		[$browser] = $this->browser();
		ProjectFactory::createOne(['code' => 'testProject']);

		$browser
			->post('/api/perf_buckets', [
				'headers' => ['Content-Type' => 'application/json'],
				'body'    => json_encode($payload),
			])
			->assertStatus(422);
	}

	/** @return array<string, array{array<string, mixed>}> */
	public static function invalidPayloads(): array
	{
		$row = [
			'bucketAt'     => '2026-03-10T14:37:00Z',
			'serverName'   => 'web-01',
			'host'         => 'www.site.com',
			'path'         => '/user/{id}',
			'hits'         => 1,
			'sumDuration'  => 0.1,
			'sumUser'      => 0.0,
			'sumSys'       => 0.0,
			'maxDuration'  => 0.1,
			'sumMem'       => 0,
			'maxMem'       => 0,
			'clientErrors' => 0,
			'serverErrors' => 0,
			'histogram'    => [0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0],
			'extra'        => [],
		];

		return [
			'no project'          => [['rows' => [$row]]],
			'no rows'             => [['projectCode' => 'testProject', 'rows' => []]],
			'no path'             => [['projectCode' => 'testProject', 'rows' => [['path' => ''] + $row]]],
			'no hits'             => [['projectCode' => 'testProject', 'rows' => [['hits' => 0] + $row]]],
			'negative sum'        => [['projectCode' => 'testProject', 'rows' => [['sumDuration' => -1.0] + $row]]],
			'short histogram'     => [['projectCode' => 'testProject', 'rows' => [['histogram' => [1, 2, 3]] + $row]]],
			'negative bin'        => [[
				'projectCode' => 'testProject',
				'rows'        => [['histogram' => array_fill(0, 16, -1)] + $row],
			]],
			'errors beyond hits'  => [[
				'projectCode' => 'testProject',
				'rows'        => [['clientErrors' => 2, 'serverErrors' => 2] + $row],
			]],
			'hostile extra key'   => [[
				'projectCode' => 'testProject',
				'rows'        => [['extra' => ['a"."b' => 1]] + $row],
			]],
			'extra is not a number' => [[
				'projectCode' => 'testProject',
				'rows'        => [['extra' => ['sq' => 'many']] + $row],
			]],
		];
	}

	/** @param list<array<string, mixed>> $rows */
	private function payload(array $rows, string $projectCode = 'testProject'): array
	{
		return [
			'headers' => ['Content-Type' => 'application/json'],
			'body'    => json_encode(['projectCode' => $projectCode, 'rows' => $rows]),
		];
	}

	/** @return array<string, mixed> */
	private function row(
		string $path = '/user/{id}',
		int $hits = 1,
		float $sumDuration = 0.1,
		?array $extra = null,
	): array {
		$histogram    = HistogramBins::empty();
		$histogram[8] = $hits;

		return [
			'bucketAt'     => '2026-03-10T14:37:00Z',
			'serverName'   => 'web-01',
			'host'         => 'www.site.com',
			'path'         => $path,
			'hits'         => $hits,
			'sumDuration'  => $sumDuration,
			'sumUser'      => 0.05,
			'sumSys'       => 0.01,
			'maxDuration'  => $sumDuration,
			'sumMem'       => 1048576,
			'maxMem'       => 1048576,
			'clientErrors' => 0,
			'serverErrors' => 0,
			'histogram'    => $histogram,
			'extra'        => $extra ?? [],
		];
	}

	private function stored(
		string $path = '/user/{id}',
		PerfGranularity $granularity = PerfGranularity::Minute,
	): ?PerfBucket {
		$container = self::getContainer();
		$container->get(EntityManagerInterface::class)->clear();

		return $container->get(PerfBucketRepository::class)->findOneByKey(
			$granularity,
			new DateTimeImmutable('2026-03-10 14:37:00'),
			$container->get(\BugCatcher\Repository\ProjectRepository::class)->findOneBy(['code' => 'testProject']),
			'web-01',
			'www.site.com',
			$path,
		);
	}
}
