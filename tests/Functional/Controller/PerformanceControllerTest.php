<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Functional\Controller;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\User;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\SqlMetrics;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\UserFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Functional\apiTestHelper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class PerformanceControllerTest extends KernelTestCase
{
	use apiTestHelper;

	public function testTheChartsOfOneProjectAreAPageOfTheirOwn(): void
	{
		$project = ProjectFactory::createOne(['enabled' => true, 'name' => 'Checkout'])->_real();
		$this->measured($project);
		$id = $project->getId();

		[$browser] = $this->browser();
		$browser
			->actingAs($this->userOf($project))
			->visit("/performance/{$id}")
			->assertSuccessful()
			->assertSeeIn('title', 'Checkout')
			// every panel of the page, and the charts they drew
			->assertSee('Heaviest routes')
			->assertSee('Database')
			->assertContains('class="atelier-chart"');
	}

	public function testTheAllProjectsPageIsTheOneTheNavLinksTo(): void
	{
		$project = ProjectFactory::createOne(['enabled' => true])->_real();
		$this->measured($project);

		[$browser] = $this->browser();
		$browser
			->actingAs($this->userOf($project))
			->visit('/performance')
			->assertSuccessful()
			->assertSee('all projects')
			->assertContains('class="atelier-chart"');
	}

	/**
	 * A uuid in a URL is a guess anybody can make, and these charts are the shape of somebody
	 * else's traffic.
	 */
	public function testAProjectTheUserIsNotOnIsNotThere(): void
	{
		$mine     = ProjectFactory::createOne(['enabled' => true])->_real();
		$somebody = ProjectFactory::createOne(['enabled' => true])->_real();
		$id       = $somebody->getId();

		[$browser] = $this->browser();
		$browser
			->actingAs($this->userOf($mine))
			->visit("/performance/{$id}")
			->assertStatus(404);
	}

	/**
	 * The link a regression writes, end to end: a window in the past, one route, and the row to
	 * scroll to.
	 *
	 * The page took no query parameters at all before this, so the only view it could offer was the
	 * last hour of everything - which is the one view that cannot answer what a regression asks.
	 */
	public function testTheWindowAndTheRouteComeFromTheUrl(): void
	{
		$project = ProjectFactory::createOne(['enabled' => true, 'name' => 'Checkout'])->_real();
		$at      = $this->measured($project);
		$id      = $project->getId();

		[$browser] = $this->browser();
		$browser
			->actingAs($this->userOf($project))
			->visit("/performance/{$id}?at={$at->format('Y-m-d\TH:i')}&hours=1&path=/checkout")
			->assertSuccessful()
			// the pickers hold the anchored window rather than being empty
			->assertContains('value="'.$at->format('Y-m-d').'"')
			->assertContains('data-controller="perf-range"')
			// and the charts are of that one route, which is what the chip says
			->assertSee('/checkout')
			->assertContains('id="perf-path-'.PerfBucket::hashPath('/checkout').'"');
	}

	/**
	 * A hand-edited URL, an old bookmark or a link a mail client mangled must not be a 500 - this
	 * page is often behind no firewall at all, and `hours` reaches a chart that is inline SVG in
	 * the document.
	 */
	public function testAHostileWindowRendersRatherThanBreaking(): void
	{
		$project = ProjectFactory::createOne(['enabled' => true])->_real();
		$this->measured($project);
		$id = $project->getId();

		[$browser] = $this->browser();
		$browser
			->actingAs($this->userOf($project))
			->visit("/performance/{$id}?at=not-a-date&hours=-5")
			->assertSuccessful()
			->visit("/performance/{$id}?at=&hours=100000")
			->assertSuccessful();
	}

	private function userOf(Project $project): User
	{
		return UserFactory::createOne([
			'enabled' => true,
			'roles'   => ['ROLE_DEVELOPER'],
			'projects' => [$project],
		])->_real();
	}

	private function measured(Project $project): DateTimeImmutable
	{
		$at = new DateTimeImmutable('-5 minutes');

		$histogram                               = HistogramBins::empty();
		$histogram[HistogramBins::binFor(300.0)] = 10;

		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				PerfGranularity::Minute,
				$at,
				$project,
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
				durationHistogram: $histogram,
				extra: [SqlMetrics::QUERIES => 120, SqlMetrics::SECONDS => 0.4],
			),
		]);

		return $at;
	}
}
