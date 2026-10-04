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

	/**
	 * One read of the window feeds all four, and the panel renders them all.
	 *
	 * Charts are counted by the library's root class rather than by `<svg`: every hint beside a
	 * heading is an inline icon, and those are SVGs too.
	 */
	public function testThePanelInlinesThem(): void
	{
		$this->measured();

		$html = $this->render();

		$this->assertSame(5, substr_count($html, 'class="atelier-chart"'));
		$this->assertStringContainsString('var(--bc-perf-', $html);
	}

	public function testAWindowWithNothingInItSaysSoRatherThanDrawingEmptyAxes(): void
	{
		$html = $this->render();

		$this->assertStringNotContainsString('class="atelier-chart"', $html);
		$this->assertStringContainsString('No performance data', $html);
	}

	/** Every chart says what it means, and the explanation is a translation rather than a literal. */
	public function testEveryChartCarriesAHint(): void
	{
		$this->measured();

		$html = $this->render();

		$this->assertSame(5, substr_count($html, 'class="bc-hint__bubble"'));
		$this->assertStringContainsString('the mean and the 95th percentile', $html);
	}

	/**
	 * The dashboard shows every project until somebody picks one, so that is the view it has to
	 * answer first: one set of charts over everything the server watches.
	 */
	public function testWithoutAProjectItChartsThemAll(): void
	{
		$this->measured();
		$other = ProjectFactory::createOne()->_real();
		$this->measuredFor($other, hits: 4);

		$component = $this->mountTwigComponent('PerfOverview', []);
		$this->assertInstanceOf(PerfOverview::class, $component);

		$this->assertSame(14, $component->getSeries()->hits());
		$this->assertStringContainsString('<svg', (string)$this->renderTwigComponent('PerfOverview', []));
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
		$this->measuredFor($this->project, 10);
	}

	private function measuredFor(Project $project, int $hits): void
	{
		$histogram                               = HistogramBins::empty();
		$histogram[HistogramBins::binFor(300.0)] = $hits;

		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				PerfGranularity::Minute,
				new DateTimeImmutable('-10 minutes'),
				$project,
				'web-01',
				'www.site.com',
				'/checkout',
				hits: $hits,
				sumDuration: 0.3 * $hits,
				sumUser: 0.12 * $hits,
				sumSys: 0.03 * $hits,
				maxDuration: 0.4,
				sumMem: 1_048_576 * $hits,
				maxMem: 2_097_152,
				clientErrors: 1,
				serverErrors: 1,
				durationHistogram: $histogram,
			),
		]);
	}
}
