<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Functional\Controller;

use BugCatcher\Controller\Admin\ProjectCrudController;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfProfile;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Functional\apiTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;

/**
 * The workload is only reachable through the admin, and the admin is not what phpunit usually
 * renders - `tests/skeleton/e2e.sh` gets as far as `/admin` and stops at the index. A `ChoiceField`
 * over a PHP enum is exactly the kind of thing that renders fine until EasyAdmin is asked for the
 * form, so this is the one page that has to be opened.
 */
class ProjectWorkloadTest extends KernelTestCase
{
	use apiTestHelper;
	use Factories;

	public function testTheEditFormOffersBothWorkloads(): void
	{
		$project = ProjectFactory::createOne(['perfEnabled' => true, 'perfProfile' => null])->_real();

		[$browser] = $this->browser([]);

		$browser
			->get(sprintf(
				'/admin?crudAction=edit&crudControllerFqcn=%s&entityId=%s',
				urlencode(ProjectCrudController::class),
				$project->getId()->toString(),
			))
			->assertStatus(200)
			->assertSeeElement('select#Project_perfProfile');

		$options = null;
		$browser->use(function (Crawler $crawler) use (&$options): void {
			$options = $crawler->filter('#Project_perfProfile option')->each(
				static fn (Crawler $option): string => (string)$option->attr('value'),
			);
		});

		// the backing values, not the indexes Symfony falls back to when the choices are enum
		// objects it cannot name, and no blank option - a null workload already means Web
		$this->assertSame(['web', 'worker'], $options);
	}

	/**
	 * Null in the column is Web, and the round trip keeps whichever was chosen. The null case is
	 * the one that matters: it is every project of an installation that upgraded into this column.
	 */
	public function testTheWorkloadSurvivesTheRoundTrip(): void
	{
		$project = ProjectFactory::createOne(['perfProfile' => null])->_real();
		$em      = self::getContainer()->get(EntityManagerInterface::class);

		$this->assertSame(PerfProfile::Web, $project->getPerfProfile(), 'an unset workload is a web project');

		$project->setPerfProfile(PerfProfile::Worker);
		$em->flush();
		$em->clear();

		$reloaded = $em->getRepository(Project::class)->find($project->getId());
		$this->assertInstanceOf(Project::class, $reloaded);
		$this->assertSame(PerfProfile::Worker, $reloaded->getPerfProfile());
	}
}
