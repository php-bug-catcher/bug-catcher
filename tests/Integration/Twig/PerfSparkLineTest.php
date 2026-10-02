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
use BugCatcher\Twig\Components\PerfSparkLine;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;

/**
 * The line next to a project in the status list: a day of p95, no axes, no JavaScript. It answers
 * one question from across the room - is this getting slower - and the page it lives on is a wall
 * monitor.
 */
class PerfSparkLineTest extends KernelTestCase
{
	use Factories;
	use InteractsWithTwigComponents;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne()->_real();
	}

	public function testItDrawsTheLastDayOfLatency(): void
	{
		$this->hour('-3 hours', msPerHit: 100);
		$this->hour('-2 hours', msPerHit: 900);

		$svg = $this->render();

		$this->assertStringContainsString('<svg', $svg);
		$this->assertStringContainsString('polyline', $svg);
	}

	/** Hard-coded colours survive a theme switch by not being there. */
	public function testItTakesItsColoursFromTheTheme(): void
	{
		$this->hour('-2 hours', msPerHit: 900);

		$svg = $this->render();

		$this->assertStringContainsString('var(--bc-spark-', $svg);
		$this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{6}/', $svg);
	}

	/**
	 * The library writes a fixed width onto the svg, which would make the chart 250px wide inside
	 * a column that is rarely 250px wide.
	 */
	public function testTheSizeIsLeftToTheStylesheet(): void
	{
		$this->hour('-2 hours', msPerHit: 900);

		$this->assertStringContainsString('viewBox="0 0 ', $this->render());
	}

	public function testAProjectThatShippedNothingDrawsAFlatLine(): void
	{
		$svg = $this->render();

		$this->assertStringContainsString('<svg', $svg);
	}

	/** An hour outside the window is not part of the shape. */
	public function testOnlyTheLastDayIsDrawn(): void
	{
		$this->hour('-30 hours', msPerHit: 5000);

		$component = $this->component();

		$this->assertSame([], $component->getSparkLineIntervals());
	}

	public function testThePointsAreTheLatencyOfEachHour(): void
	{
		$this->hour('-3 hours', msPerHit: 90);
		$this->hour('-2 hours', msPerHit: 900);

		$counts = array_map(
			static fn(object $interval): int => $interval->count,
			$this->component()->getSparkLineIntervals(),
		);

		$this->assertCount(2, $counts);
		$this->assertGreaterThanOrEqual(50, $counts[0]);
		$this->assertLessThanOrEqual(100, $counts[0]);
		$this->assertGreaterThanOrEqual(500, $counts[1]);
		$this->assertLessThanOrEqual(1000, $counts[1]);
	}

	private function render(): string
	{
		return (string)$this->renderTwigComponent('PerfSparkLine', ['project' => $this->project]);
	}

	private function component(): PerfSparkLine
	{
		$component = $this->mountTwigComponent('PerfSparkLine', ['project' => $this->project]);
		$this->assertInstanceOf(PerfSparkLine::class, $component);

		return $component;
	}

	private function hour(string $ago, int $msPerHit): void
	{
		$at                                                 = new DateTimeImmutable($ago);
		$histogram                                          = HistogramBins::empty();
		$histogram[HistogramBins::binFor((float)$msPerHit)] = 10;

		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				PerfGranularity::Hour,
				$at,
				$this->project,
				'web-01',
				'www.site.com',
				'/user/{id}',
				hits: 10,
				sumDuration: 10 * $msPerHit / 1000,
				durationHistogram: $histogram,
			),
		]);
	}
}
