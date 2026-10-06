<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Twig;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Enum\PerfProfile;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\UserFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Twig\Components\StatusList;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;

/**
 * Which row a project gets, and that the twelve columns still add up in both.
 */
class StatusListRowTest extends KernelTestCase
{
	use Factories;
	use InteractsWithTwigComponents;

	public function testAProjectWithoutPerformanceKeepsTheRowItHad(): void
	{
		$project = ProjectFactory::createOne(['perfEnabled' => false])->_real();

		$list = $this->list();

		$this->assertFalse($list->usesPerf($project));
		$this->assertSame($list->components, $list->componentsFor($project));
	}

	/**
	 * A project that predates `perfProfile` has null in the column, and null is Web - so the row
	 * it gets after the upgrade is the row it had before it.
	 */
	public function testAProjectWithPerformanceAndNoWorkloadSetGetsTheWebList(): void
	{
		$project = ProjectFactory::createOne(['perfEnabled' => true, 'perfProfile' => null])->_real();

		$list = $this->list();

		$this->assertTrue($list->usesPerf($project));
		$this->assertSame(PerfProfile::Web, $project->getPerfProfile());
		$this->assertSame($list->perfComponents, $list->componentsFor($project));
		$this->assertContains('LogCount', $list->perfComponents, 'the error count is the one cell that stays');
	}

	/**
	 * The point of the whole thing: a cron box is measured, but not with Apdex.
	 *
	 * Apdex pins T at 500 ms because T and 4T have to land on histogram bin edges, and there is no
	 * cron-scale T that does - so on a worker the cell reads 0.00 in red for ever, whatever the
	 * machine is doing. The worker row asks the two questions a job can answer instead.
	 */
	public function testAWorkerProjectGetsARowWithoutApdexOrLatency(): void
	{
		$project = ProjectFactory::createOne(['perfEnabled' => true, 'perfProfile' => PerfProfile::Worker])->_real();

		$list = $this->list();

		$this->assertSame($list->workerComponents, $list->componentsFor($project));
		$this->assertNotContains('PerfApdex', $list->workerComponents);
		$this->assertNotContains('PerfLatency', $list->workerComponents);
		$this->assertContains('PerfRegressions', $list->workerComponents, 'the baseline is what a job is judged against');
		$this->assertContains('PerfThroughput', $list->workerComponents, 'a worker that stopped running is the real incident');
		$this->assertContains('LogCount', $list->workerComponents, 'the error count never leaves');
	}

	/**
	 * The workload picks between the two performance rows; it does not conjure one up. A cron box
	 * with no collector on it has nothing in `perf_bucket`, and a row of dashes is worse than the
	 * row the dashboard had before.
	 */
	public function testAWorkerProjectWithoutTheCollectorKeepsThePlainRow(): void
	{
		$project = ProjectFactory::createOne(['perfEnabled' => false, 'perfProfile' => PerfProfile::Worker])->_real();

		$list = $this->list();

		$this->assertFalse($list->usesPerf($project));
		$this->assertSame($list->components, $list->componentsFor($project));
	}

	/** Twelve columns, in every combination - a cell too many wraps the row and a wall is full of them. */
	public function testEveryRowFillsTwelveColumnsExactly(): void
	{
		$plain  = ProjectFactory::createOne(['perfEnabled' => false, 'pingCollector' => 'none'])->_real();
		$perf   = ProjectFactory::createOne(['perfEnabled' => true, 'pingCollector' => 'none'])->_real();
		$worker = ProjectFactory::createOne([
			'perfEnabled'   => true,
			'perfProfile'   => PerfProfile::Worker,
			'pingCollector' => 'none',
		])->_real();
		$this->measured($perf);
		$this->measured($worker);

		$plainRow  = ['ProjectStatus', 'LogCount', 'LogSparkLine'];
		$perfRow   = ['ProjectStatus', 'LogCount', 'PerfApdex', 'PerfLatency', 'PerfSparkLine'];
		$workerRow = ['ProjectStatus', 'LogCount', 'PerfRegressions', 'PerfThroughput', 'PerfSparkLine'];

		// a wall with no performance anywhere is the layout it always had
		$this->assertSame(12, $this->columnsOf($plain, false, $plainRow));
		// and one where some project reports latency puts every row on the same grid
		$this->assertSame(12, $this->columnsOf($plain, true, $plainRow));
		$this->assertSame(12, $this->columnsOf($perf, true, $perfRow));
		// the worker row cuts the same twelve differently, so the two sit side by side
		$this->assertSame(12, $this->columnsOf($worker, true, $workerRow));
	}

	/**
	 * The point of the shared grid: a card without latency has to break its twelve columns at the
	 * same places as one with it, or the numbers do not line up down the wall - and a wall of
	 * projects is read by scanning a column, not by reading each card.
	 */
	public function testTheNameAndTheErrorCountLineUpAcrossBothKindsOfRow(): void
	{
		$plain = ProjectFactory::createOne(['perfEnabled' => false, 'pingCollector' => 'none'])->_real();
		$perf  = ProjectFactory::createOne(['perfEnabled' => true, 'pingCollector' => 'none'])->_real();
		$this->measured($perf);

		foreach (['ProjectStatus' => 4, 'LogCount' => 1] as $component => $span) {
			$this->assertSame($span, $this->columnsOf($plain, true, [$component]), $component . ' on the plain row');
			$this->assertSame($span, $this->columnsOf($perf, true, [$component]), $component . ' on the perf row');
		}
	}

	/**
	 * One project with latency switched on is enough to put the whole wall on the tighter grid.
	 *
	 * Asked of the user's own projects, not of every project in the database - the wall only ever
	 * draws what that user is on, so that is what its layout follows.
	 */
	public function testTheWallIsDenseAsSoonAsAnyProjectReportsLatency(): void
	{
		// enabled explicitly: the factory randomises it, and getActiveProjects() leaves out the
		// disabled ones - so a random false here would make this test pass for the wrong reason
		$plain = ProjectFactory::createOne(['enabled' => true, 'perfEnabled' => false, 'pingCollector' => 'none'])->_real();

		$this->loginUser(UserFactory::createOne(['enabled' => true, 'projects' => [$plain]])->_real());
		$this->assertFalse($this->list()->isDense(), 'no project of this user has it on');

		$perf = ProjectFactory::createOne(['enabled' => true, 'perfEnabled' => true, 'pingCollector' => 'none'])->_real();

		$this->loginUser(UserFactory::createOne(['enabled' => true, 'projects' => [$plain, $perf]])->_real());
		$this->assertTrue($this->list()->isDense());
	}

	/** The row says what the numbers mean, and the Apdex carries the colour. */
	public function testThePerfRowPrintsTheScoreAndTheLatency(): void
	{
		$project = ProjectFactory::createOne(['perfEnabled' => true])->_real();
		$this->measured($project);

		$apdex   = (string)$this->renderTwigComponent('PerfApdex', ['project' => $project, 'dense' => true]);
		$latency = (string)$this->renderTwigComponent('PerfLatency', ['project' => $project, 'dense' => true]);

		$this->assertStringContainsString('1.00', $apdex);
		$this->assertStringContainsString('text-ok', $apdex);
		$this->assertStringContainsString('excellent', $apdex);

		// the p95 is estimated inside the bin the requests landed in, so it is printed as a
		// duration somewhere in that bin rather than as the exact 300 ms they took
		$this->assertMatchesRegularExpression('/>\d+(\.\d+)? ms</', $latency);
	}

	/** A project that shipped nothing says nothing, rather than printing a reassuring zero. */
	public function testAProjectThatShippedNothingPrintsADash(): void
	{
		$project = ProjectFactory::createOne(['perfEnabled' => true])->_real();

		$this->assertStringContainsString('–', (string)$this->renderTwigComponent('PerfApdex', ['project' => $project]));
		$this->assertStringContainsString('–', (string)$this->renderTwigComponent('PerfLatency', ['project' => $project]));
		$this->assertStringContainsString('–', (string)$this->renderTwigComponent('PerfThroughput', ['project' => $project]));
	}

	public function testTheOptionalCellsAreBuiltAndWorkTheSameWay(): void
	{
		$project = ProjectFactory::createOne(['perfEnabled' => true])->_real();
		$this->measured($project, hits: 120);

		$throughput = (string)$this->renderTwigComponent('PerfThroughput', ['project' => $project]);
		$regressions = (string)$this->renderTwigComponent('PerfRegressions', ['project' => $project]);

		// 120 requests in a one-hour window, and the unit is a span of its own
		$this->assertStringContainsString('>2/min', $throughput);
		$this->assertStringContainsString('/min', $throughput);
		$this->assertStringContainsString('>0<', $regressions);
	}

	/**
	 * Rows and not occurrences: one route regressing for an hour is one record with a count of
	 * twelve, and "12" on the row would read as twelve routes.
	 */
	public function testTheRegressionCellCountsRoutesAndIgnoresClearedOnes(): void
	{
		$project = ProjectFactory::createOne(['perfEnabled' => true])->_real();
		$em      = self::getContainer()->get(EntityManagerInterface::class);

		$open = new RecordPerformance($project, '/checkout', 'p95', PerfUnit::Milliseconds, 210.0, 3100.0, new DateTimeImmutable('-10 minutes'));
		$done = new RecordPerformance($project, '/login', 'p95', PerfUnit::Milliseconds, 80.0, 900.0, new DateTimeImmutable('-20 minutes'));
		$done->setStatus('resolved');

		$em->persist($open);
		$em->persist($done);
		$em->flush();

		$html = (string)$this->renderTwigComponent('PerfRegressions', ['project' => $project]);

		$this->assertStringContainsString('>1<', $html);
		$this->assertStringContainsString('text-warn', $html);
	}

	private function list(): StatusList
	{
		$list = self::getContainer()->get(StatusList::class);
		$this->assertInstanceOf(StatusList::class, $list);

		return $list;
	}

	/**
	 * @param list<string> $components
	 * @return int the col-span classes the row's cells asked for, added up
	 */
	private function columnsOf(Project $project, bool $dense, array $components): int
	{
		$total = 0;

		foreach ($components as $component) {
			$html = (string)$this->renderTwigComponent($component, ['project' => $project, 'dense' => $dense]);

			$this->assertSame(1, preg_match('/col-span-(\d+)/', $html, $span), $component . ' has no span');
			$total += (int)$span[1];
		}

		return $total;
	}

	private function measured(Project $project, int $hits = 10): void
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
				maxDuration: 0.4,
				durationHistogram: $histogram,
			),
		]);
	}
}
