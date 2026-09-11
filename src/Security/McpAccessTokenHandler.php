<?php

namespace BugCatcher\Security;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Authenticates the MCP endpoint against one static Bearer token, configured as
 * `bug_catcher.mcp.access_token`.
 *
 * The token is not tied to a `User`, so it is not scoped to any project - whoever holds it reads
 * the errors of every project on the instance. Narrowing that down to `User::getActiveProjects()`
 * is the next step, and the reason the identifier below is a fixed one rather than something
 * derived from the token.
 */
final class McpAccessTokenHandler implements AccessTokenHandlerInterface
{
	public const USER_IDENTIFIER = 'mcp';

	/**
	 * @param string|null $accessToken null when `MCP_ACCESS_TOKEN` is not set in the environment
	 */
	public function __construct(private readonly ?string $accessToken) {}

	public function getUserBadgeFrom(string $accessToken): UserBadge {
		// An unconfigured server refuses everyone. Treating "no token configured" as "everything
		// matches" would publish every error of every project the day someone forgets the env var.
		if ($this->accessToken === null || trim($this->accessToken) === '') {
			throw new BadCredentialsException('The MCP access token is not configured.');
		}

		if (!hash_equals($this->accessToken, $accessToken)) {
			throw new BadCredentialsException('Invalid MCP access token.');
		}

		return new UserBadge(
			self::USER_IDENTIFIER,
			// carries its own user, so the endpoint needs no user provider of its own
			static fn(): InMemoryUser => new InMemoryUser(self::USER_IDENTIFIER, null, ['ROLE_MCP']),
		);
	}
}
