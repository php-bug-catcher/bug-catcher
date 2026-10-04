<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Twig;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Tests\App\Factory\ProjectFactory;
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

	public function testAProjectWithPerformanceGetsTheOtherList(): void
	{
		$project = ProjectFactory::createOne(['perfEnabled' => true])->_real();

		$list = $this->list();

		$this->assertTrue($list->usesPerf($project));
		$this->assertSame($list->perfComponents, $list->componentsFor($project));
		$this->assertContains('LogCount', $list->perfComponents, 'the error count is the one cell that stays');
	}

	/** Twelve columns, in both rows - a cell too many wraps the row and a wall monitor is full of them. */
	public function testBothRowsFillTwelveColumnsExactly(): void
	{
		$plain = ProjectFactory::createOne(['perfEnabled' => false, 'pingCollector' => 'none'])->_real();
		$perf  = ProjectFactory::createOne(['perfEnabled' => true, 'pingCollector' => 'none'])->_real();
		$this->measured($perf);

		$this->assertSame(12, $this->columnsOf($plain, false, ['ProjectStatus', 'LogCount', 'LogSparkLine']));
		$this->assertSame(12, $this->columnsOf($perf, true, ['ProjectStatus', 'LogCount', 'PerfApdex', 'PerfLatency', 'PerfSparkLine']));
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
