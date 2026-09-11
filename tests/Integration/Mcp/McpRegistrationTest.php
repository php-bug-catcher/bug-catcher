<?php

namespace BugCatcher\Tests\Integration\Mcp;

use BugCatcher\Tests\App\KernelTestCase;
use Mcp\Capability\Registry;
use Symfony\Component\Routing\RouterInterface;

/**
 * The tools live inside a bundle, not in the application's own `src/`, so it is worth pinning that
 * the `#[McpTool]` attribute still reaches the server: the bundle collects tools from the
 * `mcp.tool` tag, which only lands on a service that is autoconfigured. `config/services.php`
 * registers the whole `BugCatcher\` namespace with `autoconfigure()`, which is what makes it work -
 * drop that and every tool silently disappears from the server.
 */
class McpRegistrationTest extends KernelTestCase {

	protected function setUp(): void {
		self::bootKernel();
	}

	public function testTheToolsOfTheBundleAreExposedByTheServer() {
		$registry = self::getContainer()->get('mcp.server.bug_catcher.registry');
		$this->assertInstanceOf(Registry::class, $registry);

		// the registry is filled lazily: the tools collected at compile time sit on the server
		// builder, and only reach the registry when the builder assembles the server
		self::getContainer()->get('mcp.server.bug_catcher');

		$this->assertContains('list_projects', array_keys($registry->getTools()->references));
	}

	public function testTheServerAnswersOnTheConfiguredPath() {
		$route = self::getContainer()->get(RouterInterface::class)
			->getRouteCollection()
			->get('_mcp_endpoint_bug_catcher');

		$this->assertNotNull($route, 'The mcp routing loader did not register the endpoint.');
		$this->assertSame('/mcp', $route->getPath());
	}
}
