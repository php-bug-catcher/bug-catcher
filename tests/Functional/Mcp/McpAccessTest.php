<?php

namespace BugCatcher\Tests\Functional\Mcp;

use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Functional\apiTestHelper;

/**
 * The endpoint hands out the errors of every project on the instance, so the token is the only
 * thing between a stranger and them.
 */
class McpAccessTest extends KernelTestCase {
	use apiTestHelper;
	use McpJsonRpc;

	public function testARequestWithoutATokenIsRefused() {
		[$browser] = $this->browser();

		$browser
			->post('/mcp', $this->rpcOptions($this->initializeRequest()))
			->assertStatus(401);
	}

	public function testARequestWithTheWrongTokenIsRefused() {
		[$browser] = $this->browser();

		$browser
			->post('/mcp', $this->rpcOptions($this->initializeRequest(), 'not-the-token'))
			->assertStatus(401);
	}

	public function testTheConfiguredTokenGetsIn() {
		[$browser] = $this->browser();

		$browser
			->post('/mcp', $this->rpcOptions($this->initializeRequest(), self::TOKEN))
			->assertSuccessful();
	}
}
