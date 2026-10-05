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
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;

/**
 * The line next to a project in the status list: a day of p95, no axes, no JavaScript. It answers
 * one question from across the room - is this slow - and the page it lives on is a wall monitor.
 *
 * Two of its answers used to be wrong. The scale was the window's own maximum, so the tallest hour
 * of every project reached the top of the chart and was painted the same alarming red whether its
 * p95 was 20ms or 2s. And an hour with no traffic was dropped rather than filled, which the
 * library drew along the very bottom - an idle Sunday and an outage rendered identically.
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

	/**
	 * One point per hour of the window, whether or not that hour reported anything. The window is
	 * 24 hours snapped out to whole hours, so it is 25 buckets unless `now` lands exactly on one.
	 */
	public function testThereIsOnePointPerHourOfTheWindow(): void
	{
		$values = $this->component()->getSlotValues();

		$this->assertGreaterThanOrEqual(24, count($values));
		$this->assertLessThanOrEqual(25, count($values));
	}

	public function testAProjectThatShippedNothingDrawsAFlatLine(): void
	{
		$values = $this->component()->getSlotValues();

		$this->assertSame(array_fill(0, count($values), 0.0), $values);
		$this->assertStringContainsString('<svg', $this->render());
	}

	/** An hour outside the window is not part of the shape. */
	public function testOnlyTheLastDayIsDrawn(): void
	{
		$this->hour('-30 hours', msPerHit: 5000);

		$values = $this->component()->getSlotValues();

		$this->assertSame(array_fill(0, count($values), 0.0), $values);
	}

	/**
	 * Each hour carries its own p95, and the hours after the last reading carry that reading:
	 * latency is a gauge, not a counter, so an hour with no traffic is "unchanged" rather than
	 * "instant". Dropping those hours is what used to put the right edge of the line on the floor.
	 */
	public function testEachHourCarriesItsP95AndTheQuietHoursHoldIt(): void
	{
		$this->hour('-3 hours', msPerHit: 90);
		$this->hour('-2 hours', msPerHit: 900);

		$values = $this->component()->getSlotValues();
		$first  = $this->firstReading($values);

		$this->assertGreaterThanOrEqual(50.0, $values[$first]);
		$this->assertLessThanOrEqual(100.0, $values[$first]);

		$slower = $values[$first + 1];
		$this->assertGreaterThanOrEqual(500.0, $slower);
		$this->assertLessThanOrEqual(1000.0, $slower);

		$this->assertSame(
			array_fill(0, count($values) - $first - 1, $slower),
			array_slice($values, $first + 1),
			'the last reading is held to the right edge rather than dropping to the floor',
		);
	}

	/** Nothing was measured before the first hour that reported, so the line opens on the floor. */
	public function testTheHoursBeforeTheFirstReadingAreOnTheBaseline(): void
	{
		$this->hour('-2 hours', msPerHit: 900);

		$values = $this->component()->getSlotValues();
		$first  = $this->firstReading($values);

		$this->assertSame(array_fill(0, $first, 0.0), array_slice($values, 0, $first));
	}

	/**
	 * The scale is a fixed budget - four times Apdex T - and not the window's maximum. A service
	 * answering in 90ms is at the bottom of the chart in the first colour of the ramp, which is
	 * the thing that was wrong: scaled to its own data, its worst hour was always at the top and
	 * always red.
	 */
	public function testAFastProjectIsNotDrawnAtTheTopOfTheChart(): void
	{
		$this->hour('-2 hours', msPerHit: 90);

		$points = $this->points($this->render());

		$this->assertLessThanOrEqual(5, end($points), 'a 90ms p95 belongs near the floor, not in the red band');
	}

	/** Over the budget is the top of the chart - the library would otherwise draw past it. */
	public function testLatencyOverTheBudgetClampsAtTheTop(): void
	{
		$this->hour('-2 hours', msPerHit: 5000);

		$points = $this->points($this->render());

		foreach ($points as $y) {
			$this->assertLessThanOrEqual(25, $y);
		}

		$this->assertSame(25, end($points));
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

	/** @param list<float> $values */
	private function firstReading(array $values): int
	{
		foreach ($values as $slot => $value) {
			if ($value > 0.0) {
				return $slot;
			}
		}

		self::fail('no hour of the window reported a p95');
	}

	/** @return list<int> the y of each point, counted up from the floor of the chart */
	private function points(string $svg): array
	{
		$this->assertMatchesRegularExpression('/points="[^"]+"/', $svg);
		preg_match('/points="([^"]+)"/', $svg, $matches);

		return array_map(
			static fn(string $pair): int => (int)explode(',', $pair)[1],
			explode(' ', trim($matches[1])),
		);
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
