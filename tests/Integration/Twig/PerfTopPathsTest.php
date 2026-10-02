<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Twig;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Enum\PerfTopPathGroup;
use BugCatcher\Enum\PerfTopPathSort;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Twig\Components\PerfTopPaths;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;

/**
 * The table phptop prints, over stored buckets and with its command line flags as controls.
 */
class PerfTopPathsTest extends KernelTestCase
{
	use Factories;
	use InteractsWithTwigComponents;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne()->_real();
	}

	public function testItListsTheHeaviestRoutes(): void
	{
		$this->bucket('/busy', hits: 100, msPerHit: 50);
		$this->bucket('/slow', hits: 3, msPerHit: 4000);

		$html = $this->render();

		$this->assertStringContainsString('/busy', $html);
		$this->assertStringContainsString('/slow', $html);
		// busiest first by default
		$this->assertLessThan(strpos($html, '/slow'), strpos($html, '/busy'));
	}

	public function testItSortsByWhateverTheControlsSay(): void
	{
		$this->bucket('/busy', hits: 100, msPerHit: 50);
		$this->bucket('/slow', hits: 3, msPerHit: 4000);

		$html = $this->render(['sort' => PerfTopPathSort::TotalTime]);

		$this->assertLessThan(strpos($html, '/busy'), strpos($html, '/slow'));
	}

	public function testItCanGroupByMachineInstead(): void
	{
		$this->bucket('/a', hits: 10, msPerHit: 50);
		$this->bucket('/b', hits: 10, msPerHit: 50, serverName: 'web-02');

		$html = $this->render(['group' => PerfTopPathGroup::Server]);

		$this->assertStringContainsString('web-01', $html);
		$this->assertStringContainsString('web-02', $html);
		$this->assertStringNotContainsString('/a', $html);
	}

	public function testThePerHitToggleChangesWhatIsPrinted(): void
	{
		$this->bucket('/busy', hits: 100, msPerHit: 50);

		$cumulative = $this->component();
		$this->assertSame(5000.0, $cumulative->getReport()->rows[0]->totalMs);

		$perHit = $this->component(['perHit' => true]);
		$this->assertTrue($perHit->perHit);
		$this->assertSame(50.0, $perHit->getReport()->rows[0]->msPerHit());
	}

	public function testAProjectThatShippedNothingSaysSoRatherThanDrawingAnEmptyTable(): void
	{
		$html = $this->render();

		$this->assertStringNotContainsString('<tbody', $html);
		$this->assertStringContainsString('No performance data', $html);
	}

	/**
	 * The dashboard lists every project until somebody picks one, and two applications both
	 * have a `/login`.
	 */
	public function testWithoutAProjectItAsksForOne(): void
	{
		$html = (string)$this->renderTwigComponent('PerfTopPaths', []);

		$this->assertStringContainsString('Pick a project', $html);
		$this->assertStringNotContainsString('<tbody', $html);
	}

	/** The window is a control too, and the report decides the bucket width from its width. */
	public function testTheWindowIsAControl(): void
	{
		$component = $this->component(['hours' => 24]);

		$this->assertSame(PerfGranularity::Hour, $component->getReport()->window->granularity);
		$this->assertSame(PerfGranularity::Minute, $this->component(['hours' => 1])->getReport()->window->granularity);
	}

	/** @param array<string, mixed> $props */
	private function render(array $props = []): string
	{
		return (string)$this->renderTwigComponent('PerfTopPaths', ['project' => $this->project] + $props);
	}

	/** @param array<string, mixed> $props */
	private function component(array $props = []): PerfTopPaths
	{
		$component = $this->mountTwigComponent('PerfTopPaths', ['project' => $this->project] + $props);
		$this->assertInstanceOf(PerfTopPaths::class, $component);

		return $component;
	}

	private function bucket(string $path, int $hits, int $msPerHit, string $serverName = 'web-01'): void
	{
		$histogram                                          = HistogramBins::empty();
		$histogram[HistogramBins::binFor((float)$msPerHit)] = $hits;

		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				PerfGranularity::Minute,
				new DateTimeImmutable('-10 minutes'),
				$this->project,
				$serverName,
				'www.site.com',
				$path,
				hits: $hits,
				sumDuration: $hits * $msPerHit / 1000,
				sumUser: $hits * $msPerHit / 2000,
				maxDuration: $msPerHit / 1000,
				sumMem: $hits * 1_048_576,
				maxMem: 1_048_576,
				durationHistogram: $histogram,
			),
		]);
	}
}
