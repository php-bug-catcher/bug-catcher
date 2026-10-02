<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Perf;

use ApiPlatform\Metadata\Post;
use BugCatcher\Api\Processor\PerfBucketBatchProcessor;
use BugCatcher\ApiResource\PerfBucketBatch;
use BugCatcher\Repository\ProjectRepository;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Zenstruck\Foundry\Test\Factories;

/**
 * `bug_catcher.perf.enabled: false` has to turn the ingest endpoint off without the collector
 * losing what it has already measured - hence 503 and not 404 or a silent 204. The collector only
 * advances its cursor on a 2xx, so the samples wait on the monitored machine until performance
 * monitoring is switched back on.
 */
class PerfDisabledTest extends KernelTestCase
{
	use Factories;

	public function testIngestRefusesWhilePerformanceMonitoringIsOff(): void
	{
		ProjectFactory::createOne(['code' => 'testProject']);

		$batch              = new PerfBucketBatch();
		$batch->projectCode = 'testProject';

		$this->expectException(ServiceUnavailableHttpException::class);

		$this->processor(enabled: false)->process($batch, new Post());
	}

	public function testTheRefusalComesBeforeTheProjectIsEvenLookedUp(): void
	{
		$batch              = new PerfBucketBatch();
		$batch->projectCode = 'noSuchProject';

		$this->expectException(ServiceUnavailableHttpException::class);

		$this->processor(enabled: false)->process($batch, new Post());
	}

	private function processor(bool $enabled): PerfBucketBatchProcessor
	{
		$container = self::getContainer();

		return new PerfBucketBatchProcessor(
			$container->get(ProjectRepository::class),
			new PerfBucketUpserter($container->get(EntityManagerInterface::class)),
			$enabled,
		);
	}
}
