<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Twig;

use BugCatcher\Entity\Project;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordLogFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Twig\Components\LogSparkLine;
use DateTimeImmutable;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;

/**
 * A day of errors next to a project in the status list: 96 quarter-hours, no axes, no JavaScript.
 *
 * The buckets trail `now` instead of sitting on the wall clock, which is the point of every test
 * here. The previous implementation bucketed the data one way and built the x axis another - one
 * flooring the timestamp, one rounding it - so the newest bucket was either still filling up or
 * an interval in the future that no row could land in. The line dived to the floor at the right
 * edge, and which of the two it was depended on what minute you happened to look.
 */
class LogSparkLineTest extends KernelTestCase
{
	use Factories;
	use InteractsWithTwigComponents;

	/** The component's own defaults: 24 hours of quarter-hours. */
	private const int SLOTS = 96;

	private const int INTERVAL = 900;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne(['enabled' => true])->_real();
	}

	public function testTheLineHasOnePointPerInterval(): void
	{
		$component = $this->component();

		$this->assertSame(self::SLOTS, $component->slotCount());
		$this->assertCount(self::SLOTS, $component->getSlotValues());
	}

	/** The right edge is the interval that ends this second, so what just happened is on it. */
	public function testTheNewestPointIsTheIntervalEndingNow(): void
	{
		$this->errors(3, secondsAgo: 450);

		$values = $this->component()->getSlotValues();

		$this->assertSame(3.0, end($values));
	}

	/**
	 * The regression test for the bug this replaces: no wall-clock boundary is involved, so the
	 * answer cannot depend on what minute it is. One error a minute ago is in the newest interval
	 * and one sixteen minutes ago is in the interval before it, at 10:00 and at 10:53 alike.
	 */
	public function testTheIntervalsAreMeasuredBackFromNowAndNotFromTheClock(): void
	{
		$this->errors(1, secondsAgo: 60);
		$this->errors(1, secondsAgo: 16 * 60);

		$values = $this->component()->getSlotValues();

		$this->assertSame(1.0, $values[self::SLOTS - 1]);
		$this->assertSame(1.0, $values[self::SLOTS - 2]);
	}

	public function testEachIntervalCarriesItsOwnCount(): void
	{
		foreach ([0, 1, 2, 3] as $intervalsAgo) {
			$this->errors($intervalsAgo + 1, secondsAgo: $intervalsAgo * self::INTERVAL + 450);
		}

		$values = $this->component()->getSlotValues();

		$this->assertSame([4.0, 3.0, 2.0, 1.0], array_slice($values, -4));
	}

	/** An interval nobody logged an error in had zero errors, and belongs on the baseline. */
	public function testAnIntervalWithNothingInItIsZero(): void
	{
		$this->errors(2, secondsAgo: 450);

		$values = $this->component()->getSlotValues();

		$this->assertSame(array_fill(0, self::SLOTS - 1, 0.0), array_slice($values, 0, -1));
	}

	public function testAnErrorOlderThanTheWindowIsNotDrawn(): void
	{
		$this->errors(5, secondsAgo: 25 * 3600);

		$this->assertSame(array_fill(0, self::SLOTS, 0.0), $this->component()->getSlotValues());
	}

	public function testOnlyThisProjectsErrorsAreCounted(): void
	{
		$this->errors(4, secondsAgo: 450);
		RecordLogFactory::createMany(7, [
			'date'    => new DateTimeImmutable('-450 seconds'),
			'status'  => 'new',
			'project' => ProjectFactory::createOne(['enabled' => true]),
		]);

		$values = $this->component()->getSlotValues();

		$this->assertSame(4.0, end($values));
	}

	/**
	 * The other half of the bug: `treshold` is the top of the scale, and the library it feeds does
	 * not clamp. Forty errors against a threshold of five used to be handed a y coordinate of 200
	 * on a 30px canvas, so the line left the SVG and the browser clipped it flat along the top -
	 * in the red band, identically for every project with any real traffic.
	 */
	public function testOverTheThresholdIsDrawnAtTheTopOfTheChartAndNotAboveIt(): void
	{
		$this->errors(40, secondsAgo: 450);

		$points = $this->points((string)$this->renderTwigComponent('LogSparkLine', ['project' => $this->project]));

		foreach ($points as $y) {
			$this->assertLessThanOrEqual(25, $y);
		}

		$this->assertSame(25, end($points));
	}

	/** Theme tokens survive a theme switch; a resolved colour does not. Sizing is the stylesheet's. */
	public function testTheColoursAndTheSizeAreLeftToTheStylesheet(): void
	{
		$this->errors(2, secondsAgo: 450);

		$svg = (string)$this->renderTwigComponent('LogSparkLine', ['project' => $this->project]);

		$this->assertStringContainsString('viewBox="0 0 ', $svg);
		$this->assertStringContainsString('var(--bc-spark-', $svg);
		$this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{6}/', $svg);
	}

	private function errors(int $count, int $secondsAgo): void
	{
		RecordLogFactory::createMany($count, [
			'date'    => new DateTimeImmutable("-{$secondsAgo} seconds"),
			'status'  => 'new',
			'project' => $this->project,
		]);
	}

	private function component(): LogSparkLine
	{
		$component = $this->mountTwigComponent('LogSparkLine', ['project' => $this->project]);
		$this->assertInstanceOf(LogSparkLine::class, $component);

		return $component;
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
}
