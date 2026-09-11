<?php

namespace BugCatcher\Tests\Integration\Mcp;

use BugCatcher\Mcp\Tool\ProjectTools;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

class ProjectToolsTest extends KernelTestCase {
	use Factories;

	private ProjectTools $tools;

	protected function setUp(): void {
		self::bootKernel();
		$this->tools = self::getContainer()->get(ProjectTools::class);
	}

	public function testEveryEnabledProjectIsListedWithWhatIsNeededToQueryIt() {
		ProjectFactory::createOne([
			"code"    => "shop",
			"name"    => "E-shop",
			"url"     => "https://shop.example.com",
			"enabled" => true,
		]);

		$this->assertSame([
			[
				'code' => 'shop',
				'name' => 'E-shop',
				'url'  => 'https://shop.example.com',
			],
		], $this->tools->listProjects());
	}

	/**
	 * A disabled project is one nobody reports to any more. Listing it would send the reader
	 * searching for errors that cannot arrive.
	 */
	public function testDisabledProjectsAreLeftOut() {
		ProjectFactory::createOne(["code" => "live", "enabled" => true]);
		ProjectFactory::createOne(["code" => "retired", "enabled" => false]);

		$this->assertSame(['live'], array_column($this->tools->listProjects(), 'code'));
	}

	public function testProjectsAreOrderedByCodeSoTheListIsStableBetweenCalls() {
		ProjectFactory::createOne(["code" => "zeta", "enabled" => true]);
		ProjectFactory::createOne(["code" => "alpha", "enabled" => true]);
		ProjectFactory::createOne(["code" => "mike", "enabled" => true]);

		$this->assertSame(['alpha', 'mike', 'zeta'], array_column($this->tools->listProjects(), 'code'));
	}

	/**
	 * `url` is nullable on the entity and the tool has to keep the key anyway - an absent key would
	 * make the reader guess whether the project has no URL or the tool forgot to report it.
	 */
	public function testAProjectWithoutAUrlStillReportsTheKey() {
		// `Project::setUrl()` takes no null - a project without a URL is one that never had it set
		ProjectFactory::createOne([
			"code"    => "no-url",
			"name"    => "No URL",
			"enabled" => true,
		]);

		$this->assertSame([
			['code' => 'no-url', 'name' => 'No URL', 'url' => null],
		], $this->tools->listProjects());
	}
}
