<?php

namespace BugCatcher\Tests\Unit\Security;

use BugCatcher\Security\McpAccessTokenHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class McpAccessTokenHandlerTest extends TestCase {

	public function testTheConfiguredTokenIsAccepted() {
		$badge = (new McpAccessTokenHandler('s3cret'))->getUserBadgeFrom('s3cret');

		$this->assertSame(McpAccessTokenHandler::USER_IDENTIFIER, $badge->getUserIdentifier());
	}

	public function testTheBearerIsGrantedTheMcpRoleAndNothingElse() {
		$badge = (new McpAccessTokenHandler('s3cret'))->getUserBadgeFrom('s3cret');

		$this->assertSame(['ROLE_MCP'], $badge->getUser()->getRoles());
	}

	public function testAnotherTokenIsRejected() {
		$this->expectException(BadCredentialsException::class);

		(new McpAccessTokenHandler('s3cret'))->getUserBadgeFrom('guess');
	}

	/**
	 * A token that is merely a prefix of the real one must not pass - the comparison is over the
	 * whole string, not a starts-with.
	 */
	public function testAPrefixOfTheTokenIsRejected() {
		$this->expectException(BadCredentialsException::class);

		(new McpAccessTokenHandler('s3cret'))->getUserBadgeFrom('s3c');
	}

	/**
	 * An unconfigured server has to refuse everyone. Falling through to "no token configured, so
	 * anything matches" would publish every error of every project the moment someone forgets to
	 * set MCP_ACCESS_TOKEN.
	 *
	 * @dataProvider notConfigured
	 */
	public function testAServerWithoutATokenRefusesEveryone(?string $configured, string $presented) {
		$this->expectException(BadCredentialsException::class);

		(new McpAccessTokenHandler($configured))->getUserBadgeFrom($presented);
	}

	public function notConfigured(): array {
		return [
			'unset'            => [null, 'anything'],
			'empty'            => ['', 'anything'],
			'empty, guessed'   => ['', ''],
			'unset, guessed'   => [null, ''],
			'whitespace only'  => ['   ', '   '],
		];
	}
}
