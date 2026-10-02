<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Twig;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Twig\Components\PerfOverview;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;

class PerfOverviewTest extends KernelTestCase
{
	use Factories;
	use InteractsWithTwigComponents;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne()->_real();
	}

	public function testItDrawsTheFourChartsOfTheWindow(): void
	{
		$this->measured();

		$charts = $this->component()->getCharts();

		foreach (['throughput', 'latency', 'bands', 'cpu', 'status'] as $chart) {
			$this->assertStringStartsWith('<svg', $charts[$chart], $chart);
		}
	}

	/** One read of the window feeds all four, and the panel renders them all. */
	public function testThePanelInlinesThem(): void
	{
		$this->measured();

		$html = $this->render();

		$this->assertSame(5, substr_count($html, '<svg'));
		$this->assertStringContainsString('var(--bc-perf-', $html);
	}

	public function testAWindowWithNothingInItSaysSoRatherThanDrawingEmptyAxes(): void
	{
		$html = $this->render();

		$this->assertStringNotContainsString('<svg', $html);
		$this->assertStringContainsString('No performance data', $html);
	}

	/**
	 * The dashboard shows every project until somebody picks one, and a chart of several
	 * projects' routes added together would be a chart of nothing.
	 */
	public function testWithoutAProjectItAsksForOne(): void
	{
		$html = (string)$this->renderTwigComponent('PerfOverview', []);

		$this->assertStringContainsString('Pick a project', $html);
		$this->assertStringNotContainsString('<svg', $html);
	}

	public function testTheWindowIsAControl(): void
	{
		$component = $this->component(['hours' => 24]);

		$this->assertSame(PerfGranularity::Hour, $component->getSeries()->window->granularity);
	}

	/** @param array<string, mixed> $props */
	private function render(array $props = []): string
	{
		return (string)$this->renderTwigComponent('PerfOverview', ['project' => $this->project] + $props);
	}

	/** @param array<string, mixed> $props */
	private function component(array $props = []): PerfOverview
	{
		$component = $this->mountTwigComponent('PerfOverview', ['project' => $this->project] + $props);
		$this->assertInstanceOf(PerfOverview::class, $component);

		return $component;
	}

	private function measured(): void
	{
		$histogram                               = HistogramBins::empty();
		$histogram[HistogramBins::binFor(300.0)] = 10;

		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				PerfGranularity::Minute,
				new DateTimeImmutable('-10 minutes'),
				$this->project,
				'web-01',
				'www.site.com',
				'/checkout',
				hits: 10,
				sumDuration: 3.0,
				sumUser: 1.2,
				sumSys: 0.3,
				maxDuration: 0.4,
				sumMem: 10_485_760,
				maxMem: 2_097_152,
				clientErrors: 1,
				serverErrors: 1,
				durationHistogram: $histogram,
			),
		]);
	}
}
