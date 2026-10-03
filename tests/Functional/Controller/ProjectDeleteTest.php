<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Functional\Controller;

use BugCatcher\Controller\Admin\ProjectCrudController;
use BugCatcher\Entity\Project;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordLogFactory;
use BugCatcher\Tests\App\Factory\RecordLogWithholderFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Functional\apiTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\Foundry\Test\Factories;

/**
 * The admin used to offer no way to delete a project at all - the action was removed from the
 * index, which is the only page it was reachable from. This is the round trip the button makes.
 */
class ProjectDeleteTest extends KernelTestCase
{
	use apiTestHelper;
	use Factories;

	public function testAProjectCanBeDeletedFromTheAdmin(): void
	{
		$project = ProjectFactory::createOne()->_real();
		RecordLogFactory::createOne(['project' => $project]);
		RecordLogWithholderFactory::createOne(['project' => $project]);

		[$browser] = $this->browser([]);

		$browser
			->get('/admin?crudAction=index&crudControllerFqcn=' . urlencode(ProjectCrudController::class))
			->assertStatus(200)
			->assertSeeElement('.action-delete');

		$token = null;
		$browser->use(function (Crawler $crawler) use (&$token): void {
			$token = $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
		});
		$this->assertNotNull($token, 'the index page carries the CSRF token the delete posts back');

		$browser->post(
			sprintf(
				'/admin?crudAction=delete&crudControllerFqcn=%s&entityId=%s',
				urlencode(ProjectCrudController::class),
				$project->getId()->toString(),
			),
			['body' => ['token' => $token]],
		);

		$em = self::getContainer()->get(EntityManagerInterface::class);
		$em->clear();

		$this->assertCount(0, $em->getRepository(Project::class)->findAll());
		$this->assertSame(0, (int)$em->getConnection()->fetchOne('SELECT COUNT(*) FROM record'));
		$this->assertSame(
			0,
			(int)$em->getConnection()->fetchOne('SELECT COUNT(*) FROM record_log_withholder'),
		);
	}
}
