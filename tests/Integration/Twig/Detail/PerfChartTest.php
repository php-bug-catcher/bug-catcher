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
use BugCatcher\Twig\Components\Detail\PerfChart;
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

	/**
	 * The way out of the record: the same moment and the same route, on the whole page.
	 *
	 * One chart answers "was this unusual". The next question is always "what else was happening
	 * at the time", and only `/performance` can answer it - so the link has to carry all three of
	 * the window, the route and the row to scroll to, or it lands on the last hour of everything.
	 */
	public function testItLinksToThatMomentOfThatRouteOnThePerformancePage(): void
	{
		$at = $this->measured();

		$html = $this->render($this->regression($at));

		$this->assertStringContainsString('/performance/'.$this->project->getId(), $html);
		// a slash and a colon are both legal unencoded in a query value, and that is what
		// UrlGenerator emits - asserting on the escaped forms would be asserting on a bug
		$this->assertStringContainsString('path=/checkout', $html);
		$this->assertStringContainsString('hours=1', $html);
		$this->assertStringContainsString('at='.$at->modify('-30 minutes')->format('Y-m-d\TH:i'), $html);
		$this->assertStringContainsString('#perf-path-'.PerfBucket::hashPath('/checkout'), $html);
	}

	/**
	 * `at` is the start of a window, so a link that wants the spike in the middle of an hour has to
	 * ask for the half hour before it. Centring is the link's job precisely so that a time somebody
	 * picks by hand is the time they get.
	 */
	public function testTheLinkOpensHalfAnHourBeforeTheRegressionSoTheSpikeIsCentred(): void
	{
		$at = $this->measured();

		$component = $this->mountTwigComponent('Detail:PerfChart', ['record' => $this->regression($at)]);
		$this->assertInstanceOf(PerfChart::class, $component);

		$this->assertSame(
			$at->modify('-30 minutes')->format('Y-m-d\TH:i'),
			$component->getAt(),
		);
		$this->assertSame(PerfBucket::hashPath('/checkout'), $component->getAnchor());
	}

	/** A record of another kind has no window and no route, so there is nothing to link to. */
	public function testARecordOfAnotherKindOffersNoLink(): void
	{
		$component = $this->mountTwigComponent('Detail:PerfChart', [
			'record' => RecordLogFactory::createOne(['project' => $this->project])->_real(),
		]);
		$this->assertInstanceOf(PerfChart::class, $component);

		$this->assertNull($component->getAt());
		$this->assertNull($component->getAnchor());
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
