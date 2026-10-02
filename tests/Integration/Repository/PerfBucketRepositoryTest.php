<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Repository;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\Factories;

class PerfBucketRepositoryTest extends KernelTestCase
{
	use Factories;

	private function repository(): PerfBucketRepository
	{
		return self::getContainer()->get(PerfBucketRepository::class);
	}

	public function testTheRepositoryIsAServiceOfItsOwn(): void
	{
		$this->assertInstanceOf(PerfBucketRepository::class, $this->repository());
	}

	public function testABucketIsFoundByTheKeyThatIdentifiesIt(): void
	{
		$project = ProjectFactory::createOne()->_real();
		$this->persist($this->bucket($project));

		$found = $this->repository()->findOneByKey(
			PerfGranularity::Minute,
			new DateTimeImmutable('2026-03-10 14:37:00'),
			$project,
			'web-01',
			'www.site.com',
			'/user/{id}',
		);

		$this->assertNotNull($found);
		$this->assertSame('/user/{id}', $found->getPath());
	}

	public function testTheKeyIsLookedUpByTheTimestampsBucketAndNotByTheTimestamp(): void
	{
		$project = ProjectFactory::createOne()->_real();
		$this->persist($this->bucket($project));

		$found = $this->repository()->findOneByKey(
			PerfGranularity::Minute,
			new DateTimeImmutable('2026-03-10 14:37:41.9'),
			$project,
			'web-01',
			'www.site.com',
			'/user/{id}',
		);

		$this->assertNotNull($found);
	}

	/**
	 * @dataProvider differingKeys
	 */
	public function testEveryPartOfTheKeyTellsBucketsApart(array $overrides): void
	{
		$project = ProjectFactory::createOne()->_real();
		$this->persist($this->bucket($project));

		$key = [
			'granularity' => PerfGranularity::Minute,
			'bucketAt'    => new DateTimeImmutable('2026-03-10 14:37:00'),
			'serverName'  => 'web-01',
			'host'        => 'www.site.com',
			'path'        => '/user/{id}',
			...$overrides,
		];

		$this->assertNull($this->repository()->findOneByKey(
			$key['granularity'],
			$key['bucketAt'],
			$project,
			$key['serverName'],
			$key['host'],
			$key['path'],
		));
	}

	/** @return array<string, array{array<string, mixed>}> */
	public static function differingKeys(): array
	{
		return [
			'another granularity' => [['granularity' => PerfGranularity::Hour]],
			'another minute'      => [['bucketAt' => new DateTimeImmutable('2026-03-10 14:38:00')]],
			'another machine'     => [['serverName' => 'web-02']],
			'another vhost'       => [['host' => 'api.site.com']],
			'another path'        => [['path' => '/user/{id}/edit']],
		];
	}

	public function testAnotherProjectsBucketIsNotThisProjectsBucket(): void
	{
		$project = ProjectFactory::createOne()->_real();
		$other   = ProjectFactory::createOne()->_real();
		$this->persist($this->bucket($project));

		$this->assertNull($this->repository()->findOneByKey(
			PerfGranularity::Minute,
			new DateTimeImmutable('2026-03-10 14:37:00'),
			$other,
			'web-01',
			'www.site.com',
			'/user/{id}',
		));
	}

	private function bucket(Project $project): PerfBucket
	{
		return new PerfBucket(
			PerfGranularity::Minute,
			new DateTimeImmutable('2026-03-10 14:37:00'),
			$project,
			'web-01',
			'www.site.com',
			'/user/{id}',
			hits: 3,
		);
	}

	private function persist(PerfBucket $bucket): void
	{
		$em = self::getContainer()->get(EntityManagerInterface::class);
		$em->persist($bucket);
		$em->flush();
		$em->clear();
	}
}
