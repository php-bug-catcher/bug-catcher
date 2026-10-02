<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Twig\Detail;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordLogFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;

class PerfChartTest extends KernelTestCase
{
	use Factories;
	use InteractsWithTwigComponents;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne()->_real();
	}

	public function testItDrawsTheRouteAroundTheTimeItRegressed(): void
	{
		$at = $this->measured();

		$html = $this->render($this->regression($at));

		$this->assertStringContainsString('<svg', $html);
		$this->assertStringContainsString('bc-perf-baseline', $html);
		$this->assertStringContainsString('210 ms', $html);
	}

	/**
	 * A record outlives the minutes it was found in, and later the hours too. Saying so is
	 * better than an empty pair of axes that reads as "the route was idle".
	 */
	public function testARegressionNothingIsKeptForSaysSo(): void
	{
		$html = $this->render($this->regression(new DateTimeImmutable('-400 days')));

		$this->assertStringNotContainsString('<svg', $html);
		$this->assertStringContainsString('No measurements', $html);
	}

	/**
	 * The component is configured per record class, but a misconfiguration should leave the rest
	 * of the detail page standing.
	 */
	public function testARecordOfAnotherKindDoesNotTakeThePageDown(): void
	{
		$html = $this->render(RecordLogFactory::createOne(['project' => $this->project])->_real());

		$this->assertStringNotContainsString('<svg', $html);
	}

	private function render(object $record): string
	{
		return (string)$this->renderTwigComponent('Detail:PerfChart', ['record' => $record]);
	}

	private function regression(DateTimeImmutable $at): RecordPerformance
	{
		return new RecordPerformance(
			$this->project,
			'/checkout',
			'p95',
			PerfUnit::Milliseconds,
			210.0,
			3100.0,
			$at,
		);
	}

	private function measured(): DateTimeImmutable
	{
		$at                                       = new DateTimeImmutable('-20 minutes');
		$histogram                                = HistogramBins::empty();
		$histogram[HistogramBins::binFor(3000.0)] = 10;

		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				PerfGranularity::Minute,
				$at,
				$this->project,
				'web-01',
				'www.site.com',
				'/checkout',
				hits: 10,
				sumDuration: 30.0,
				durationHistogram: $histogram,
			),
		]);

		return $at;
	}
}
