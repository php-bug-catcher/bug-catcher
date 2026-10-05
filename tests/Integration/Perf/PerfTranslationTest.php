<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Perf;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\SqlMetrics;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Translation\Translator;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;

/**
 * The performance panels speak whatever the installation speaks.
 *
 * Worth a test of its own because the failure is silent: `|trans` with no domain reads
 * `messages`, the bundle's catalogue is `BugCatcher`, and a template that gets it wrong renders
 * perfectly - in English, for ever. The same goes for the words the chart renderer paints into
 * the SVG, which no template ever sees.
 */
class PerfTranslationTest extends KernelTestCase
{
	use Factories;
	use InteractsWithTwigComponents;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();

		$this->project = ProjectFactory::createOne()->_real();
		$this->measured();

		$translator = self::getContainer()->get('translator');
		$this->assertInstanceOf(Translator::class, $translator);
		$translator->setLocale('sk');
	}

	public function testTheOverviewPanelIsTranslated(): void
	{
		$html = (string)$this->renderTwigComponent('PerfOverview', ['project' => $this->project]);

		// the headings
		$this->assertStringContainsString('Výkon', $html);
		$this->assertStringContainsString('Priepustnosť a odozva', $html);
		$this->assertStringContainsString('Pásma odozvy', $html);
		$this->assertStringContainsString('Kam sa minul čas', $html);
		$this->assertStringContainsString('Rozdelenie stavov', $html);

		// the hint behind an info dot
		$this->assertStringContainsString('aplikácia sa spomalila', $html);

		// and the legend the chart library paints into the SVG itself
		$this->assertStringContainsString('priemer', $html);
		$this->assertStringContainsString('čakanie', $html);
	}

	public function testTheTopPathsTableIsTranslated(): void
	{
		$html = (string)$this->renderTwigComponent('PerfTopPaths', ['project' => $this->project]);

		$this->assertStringContainsString('Najnáročnejšie cesty', $html);
		$this->assertStringContainsString('Zoskupiť podľa', $html);
		$this->assertStringContainsString('Na požiadavku', $html);

		// the column headings come from PerfTopPathSort::label()
		$this->assertStringContainsString('Celkový čas', $html);
		$this->assertStringContainsString('Špičková pamäť', $html);
		$this->assertStringContainsString('Najpomalší beh', $html);
	}

	public function testTheDatabasePanelIsTranslated(): void
	{
		$html = (string)$this->renderTwigComponent('PerfDatabase', ['project' => $this->project]);

		$this->assertStringContainsString('Databáza', $html);
		$this->assertStringContainsString('dotazov / požiadavku', $html);
		$this->assertStringContainsString('Dotazy a čas v databáze', $html);

		// the legends inside the SVG
		$this->assertStringContainsString('v databáze', $html);
		$this->assertStringContainsString('celé čakanie', $html);
	}

	/** The dashboard row's numbers are bare, so everything it has to say is in the tooltips. */
	/**
	 * The window control, which is shared by all three panels and so would be wrong in all three.
	 *
	 * Rendered twice because half its strings only exist once the window has been pinned: a live
	 * panel has nothing to go back to and does not draw the "Now" button at all.
	 */
	public function testTheWindowControlIsTranslated(): void
	{
		$live = (string)$this->renderTwigComponent('PerfOverview', ['project' => $this->project]);

		$this->assertStringContainsString('Deň', $live);
		$this->assertStringContainsString('Čas', $live);

		$anchored = (string)$this->renderTwigComponent('PerfOverview', [
			'project' => $this->project,
			'at'      => (new DateTimeImmutable('-3 hours'))->format('Y-m-d\TH:i'),
		]);

		$this->assertStringContainsString('Teraz', $anchored);
		$this->assertStringContainsString('Znova sledovať hodiny', $anchored);
	}

	/** And what a regression's link adds to the page: the chip, and the row that is not there. */
	public function testTheRouteFilterAndItsWayOutAreTranslated(): void
	{
		$filtered = (string)$this->renderTwigComponent('PerfOverview', [
			'project' => $this->project,
			'path'    => '/checkout',
		]);

		$this->assertStringContainsString('Zobraziť všetky cesty', $filtered);

		$missing = (string)$this->renderTwigComponent('PerfTopPaths', [
			'project' => $this->project,
			'path'    => '/never-called',
		]);

		$this->assertStringContainsString('nie je medzi najvyťaženejšími riadkami', $missing);
	}

	public function testTheDashboardRowCellsAreTranslated(): void
	{
		$apdex   = (string)$this->renderTwigComponent('PerfApdex', ['project' => $this->project]);
		$latency = (string)$this->renderTwigComponent('PerfLatency', ['project' => $this->project]);

		$this->assertStringContainsString('výborné', $apdex);
		$this->assertStringContainsString('sa ráta celá', $apdex);
		$this->assertStringContainsString('9 z 10 požiadaviek', $latency);
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
				// so the database panel has something to draw rather than the "nobody counted
				// these" message, which is a different string
				extra: [SqlMetrics::QUERIES => 120, SqlMetrics::SECONDS => 0.4],
			),
		]);
	}
}
