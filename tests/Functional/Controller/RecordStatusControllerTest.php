<?php

namespace BugCatcher\Tests\Functional\Controller;

use BugCatcher\Entity\RecordLog;
use BugCatcher\Tests\App\Factory\RecordLogFactory;
use BugCatcher\Tests\App\Factory\UserFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Functional\apiTestHelper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;

class RecordStatusControllerTest extends KernelTestCase
{
	use apiTestHelper;

	public function testDeveloperResolvesTheWholeGroupFromTheDetailPage(): void {
		$older = RecordLogFactory::createOne([
			"date"    => new DateTimeImmutable('-2 hours'),
			"hash"    => "same-hash",
			"message" => "boom",
			"status"  => "new",
		]);
		$newer = RecordLogFactory::createOne([
			"date"    => new DateTimeImmutable('-1 hour'),
			"hash"    => "same-hash",
			"message" => "boom",
			"project" => $older->getProject(),
			"status"  => "new",
		]);

		$em    = self::getContainer()->get(EntityManagerInterface::class);
		$id    = $newer->getId();
		$token = null;
		[$browser] = $this->browser();
		$browser
			->actingAs(UserFactory::createOne(["roles" => ["ROLE_DEVELOPER"]])->_real())
			->visit("/detail/{$id}")
			->assertSuccessful()
			->use(function (Crawler $crawler) use (&$token) {
				$token = $crawler->filter('form input[name="_token"]')->first()->attr('value');
			})
			->interceptRedirects()
			->post("/detail/{$id}/status/resolved", ['body' => ['_token' => $token]])
			->assertRedirectedTo("/detail/{$id}");

		$em->clear();
		$repo = $em->getRepository(RecordLog::class);
		$this->assertSame('resolved', $repo->find($newer->getId())->getStatus());
		$this->assertSame('resolved', $repo->find($older->getId())->getStatus(), 'the whole group has to be cleared, not only the record on screen');
	}

	/**
	 * The buttons post through fetch() so the tab can close itself, and a redirect would only make
	 * the browser render a page that is about to disappear.
	 */
	public function testAnXhrGetsNoContentInsteadOfARedirect(): void {
		$record = RecordLogFactory::createOne(["status" => "new"]);
		$id     = $record->getId();
		$token  = null;

		[$browser] = $this->browser();
		$browser
			->actingAs(UserFactory::createOne(["roles" => ["ROLE_DEVELOPER"]])->_real())
			->visit("/detail/{$id}")
			->use(function (Crawler $crawler) use (&$token) {
				$token = $crawler->filter('form input[name="_token"]')->first()->attr('value');
			})
			->post("/detail/{$id}/status/archived", [
				'headers' => ['X-Requested-With' => 'XMLHttpRequest'],
				'body'    => ['_token' => $token],
			])
			->assertStatus(204);
	}

	public function testAnUnknownStatusIsNotRoutable(): void {
		$record = RecordLogFactory::createOne(["status" => "new"]);

		[$browser] = $this->browser();
		$browser
			->actingAs(UserFactory::createOne(["roles" => ["ROLE_DEVELOPER"]])->_real())
			->post("/detail/{$record->getId()}/status/deleted", ['body' => ['_token' => 'irrelevant']])
			->assertStatus(404);
	}

	public function testAWrongCsrfTokenIsRejected(): void {
		$record = RecordLogFactory::createOne(["status" => "new"]);

		[$browser] = $this->browser();
		$browser
			->actingAs(UserFactory::createOne(["roles" => ["ROLE_DEVELOPER"]])->_real())
			->post("/detail/{$record->getId()}/status/resolved", ['body' => ['_token' => 'nope']])
			->assertStatus(403);
	}

	public function testTheButtonsAreHiddenFromNonDevelopers(): void {
		$record = RecordLogFactory::createOne(["status" => "new"]);

		[$browser] = $this->browser();
		$browser
			->actingAs(UserFactory::createOne(["roles" => ["ROLE_CUSTOMER"]])->_real())
			->post("/detail/{$record->getId()}/status/resolved", ['body' => ['_token' => 'nope']])
			->assertStatus(403);
	}
}
