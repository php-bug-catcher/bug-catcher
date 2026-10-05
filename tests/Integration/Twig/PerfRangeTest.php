<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Twig;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Enum\PerfTopPathGroup;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\Report\Dto\PerfRange;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Twig\Components\PerfDatabase;
use BugCatcher\Twig\Components\PerfOverview;
use BugCatcher\Twig\Components\PerfTopPaths;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;

/**
 * The window a panel was asked for, and the route it was asked about.
 *
 * Both exist for one journey: a `RecordPerformance` is written at 04:00, somebody opens it at
 * nine, and the page has to show four in the morning rather than nine - for that route rather than
 * for the whole application. Before this, `/performance` could only ever show the last N hours of
 * everything, which is the one view that cannot answer the question a regression asks.
 */
final class PerfRangeTest extends KernelTestCase
{
	use Factories;
	use InteractsWithTwigComponents;

	/** Far enough back that it cannot be mistaken for the live window, still inside minute retention. */
	private const string ANCHOR = '-30 hours';

	private Project $project;

	private DateTimeImmutable $anchor;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne()->_real();

		// floored to the minute: the window is read at minute granularity and a bucket written at
		// 14:30:41 belongs to the 14:30 bucket, which is also where the anchor has to point
		$this->anchor = (new DateTimeImmutable(self::ANCHOR))->setTime(
			(int)(new DateTimeImmutable(self::ANCHOR))->format('H'),
			(int)(new DateTimeImmutable(self::ANCHOR))->format('i'),
		);
	}

	/**
	 * The point of the whole feature: a window that does not end now.
	 *
	 * Traffic thirty hours ago is invisible to every default panel, so finding it is proof the
	 * anchor was used rather than ignored.
	 */
	public function testAnAnchoredWindowReadsThePastRatherThanTheLastHour(): void
	{
		$this->measured('/checkout', 10);

		$this->assertSame(0, $this->overview()->getSeries()->hits(), 'the live window should be empty');
		$this->assertSame(10, $this->overview(['at' => $this->anchorValue()])->getSeries()->hits());
	}

	/** `at` is the start of the window, so the window is exactly the hour it names. */
	public function testTheWindowStartsAtTheAnchor(): void
	{
		$window = $this->overview(['at' => $this->anchorValue()])->getSeries()->window;

		$this->assertSame($this->anchor->format('Y-m-d H:i'), $window->from->format('Y-m-d H:i'));
		$this->assertSame(3600, $window->to->getTimestamp() - $window->from->getTimestamp());
		$this->assertSame(PerfGranularity::Minute, $window->granularity);
	}

	/**
	 * `PerfReportBuilder::timeSeries()` has taken a `$path` since it was written and nothing ever
	 * passed one, so this is the first test that the fourth argument arrives.
	 */
	public function testAPathNarrowsTheChartsToOneRoute(): void
	{
		$this->measured('/checkout', 10);
		$this->measured('/cart', 4);

		$both = $this->overview(['at' => $this->anchorValue()]);
		$one  = $this->overview(['at' => $this->anchorValue(), 'path' => '/checkout']);

		$this->assertSame(14, $both->getSeries()->hits());
		$this->assertSame(10, $one->getSeries()->hits());
	}

	/** A route nobody measured is an empty chart, not every route. */
	public function testAnUnknownPathDrawsNothingRatherThanEverything(): void
	{
		$this->measured('/checkout', 10);

		$component = $this->overview(['at' => $this->anchorValue(), 'path' => '/no-such-route']);

		$this->assertSame(0, $component->getSeries()->hits());
		$this->assertStringContainsString('No performance data', $this->renderOverview([
			'at' => $this->anchorValue(), 'path' => '/no-such-route',
		]));
	}

	/** Every panel on the page takes the same two props, or the link would only move one of them. */
	public function testTheOtherTwoPanelsTakeTheSameWindow(): void
	{
		$this->measured('/checkout', 10);

		$topPaths = $this->mountTwigComponent('PerfTopPaths', [
			'project' => $this->project, 'at' => $this->anchorValue(),
		]);
		$database = $this->mountTwigComponent('PerfDatabase', [
			'project' => $this->project, 'at' => $this->anchorValue(),
		]);

		$this->assertInstanceOf(PerfTopPaths::class, $topPaths);
		$this->assertInstanceOf(PerfDatabase::class, $database);

		$this->assertSame(10, $topPaths->getReport()->rows[0]->hits);
		$this->assertSame(10, $database->getSeries()->hits());
	}

	/**
	 * The row a regression's link scrolls to, and the mark that says which one it was.
	 *
	 * The anchor is the path's hash rather than the path: `/user/{id}` has a brace in it and a
	 * slash is a path separator, neither of which belongs in a URL fragment.
	 */
	public function testTheRowOfTheLinkedRouteIsMarkedAndCanBeScrolledTo(): void
	{
		$this->measured('/checkout', 10);
		$this->measured('/cart', 4);

		$html = (string)$this->renderTwigComponent('PerfTopPaths', [
			'project' => $this->project, 'at' => $this->anchorValue(), 'path' => '/checkout',
		]);

		$this->assertStringContainsString('id="perf-path-'.PerfBucket::hashPath('/checkout').'"', $html);
		$this->assertStringContainsString('id="perf-path-'.PerfBucket::hashPath('/cart').'"', $html);
		// the table still compares the route against the others - it is not filtered to one row
		$this->assertStringContainsString('/cart', $html);
		$this->assertSame(1, substr_count($html, 'ring-accent/40'));
	}

	/**
	 * A route slow enough to regress is often not a route called often enough to be heavy, so the
	 * fragment frequently points at a row that is not there. Saying so beats a link that silently
	 * does not scroll.
	 */
	public function testARouteMissingFromTheTableIsSaidToBeMissing(): void
	{
		$this->measured('/checkout', 10);

		$component = $this->mountTwigComponent('PerfTopPaths', [
			'project' => $this->project, 'at' => $this->anchorValue(), 'path' => '/rarely-called',
		]);
		$this->assertInstanceOf(PerfTopPaths::class, $component);
		$this->assertFalse($component->isPathListed());

		$html = (string)$this->renderTwigComponent('PerfTopPaths', [
			'project' => $this->project, 'at' => $this->anchorValue(), 'path' => '/rarely-called',
		]);
		$this->assertStringContainsString('not among the busiest rows', $html);
	}

	/**
	 * Grouped by anything but path a `label` is a machine or a vhost, so an `id` that looked like a
	 * route would point at the wrong kind of thing.
	 */
	public function testGroupedByServerTheRowsCarryNoRouteAnchor(): void
	{
		$this->measured('/checkout', 10);

		// the enum case rather than 'server': Live Component hydrates a backed enum from the wire,
		// but mounting one directly goes through PropertyAccess, which does not
		$html = (string)$this->renderTwigComponent('PerfTopPaths', [
			'project' => $this->project,
			'at'      => $this->anchorValue(),
			'group'   => PerfTopPathGroup::Server,
		]);

		$this->assertStringNotContainsString('id="perf-path-', $html);
	}

	/** A bad `at` renders the live window rather than a stack trace. */
	public function testAnAnchorThatIsNotOneStillRenders(): void
	{
		$component = $this->overview(['at' => 'not-a-date']);

		$this->assertTrue($component->getRange()->live);
		$this->assertStringContainsString('No performance data', $this->renderOverview(['at' => 'not-a-date']));
	}

	/** The pickers are rendered from the resolved window, so a live panel leaves them empty. */
	public function testThePickersShowTheAnchorAndAreEmptyWhileLive(): void
	{
		$anchored = $this->renderOverview(['at' => $this->anchorValue()]);

		$this->assertStringContainsString('value="'.$this->anchor->format('Y-m-d').'"', $anchored);
		$this->assertStringContainsString('value="'.$this->anchor->format('H:i').'"', $anchored);
		$this->assertStringContainsString('data-controller="perf-range"', $anchored);
		// the way back to a window that follows the clock, offered only once there is one
		$this->assertStringContainsString('perf-range#now', $anchored);

		$this->assertStringNotContainsString('perf-range#now', $this->renderOverview());
	}

	private function anchorValue(): string
	{
		return $this->anchor->format(PerfRange::AT_FORMAT);
	}

	/** @param array<string, mixed> $props */
	private function overview(array $props = []): PerfOverview
	{
		$component = $this->mountTwigComponent('PerfOverview', ['project' => $this->project] + $props);
		$this->assertInstanceOf(PerfOverview::class, $component);

		return $component;
	}

	/** @param array<string, mixed> $props */
	private function renderOverview(array $props = []): string
	{
		return (string)$this->renderTwigComponent('PerfOverview', ['project' => $this->project] + $props);
	}

	private function measured(string $path, int $hits): void
	{
		$histogram                               = HistogramBins::empty();
		$histogram[HistogramBins::binFor(300.0)] = $hits;

		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				PerfGranularity::Minute,
				$this->anchor->modify('+5 minutes'),
				$this->project,
				'web-01',
				'www.site.com',
				$path,
				hits: $hits,
				sumDuration: 0.3 * $hits,
				sumUser: 0.12 * $hits,
				sumSys: 0.03 * $hits,
				maxDuration: 0.4,
				sumMem: 1_048_576 * $hits,
				maxMem: 2_097_152,
				durationHistogram: $histogram,
			),
		]);
	}
}
