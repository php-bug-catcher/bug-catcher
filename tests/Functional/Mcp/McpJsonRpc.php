<?php

namespace BugCatcher\Tests\Functional\Mcp;

use Zenstruck\Browser\KernelBrowser;

/**
 * Speaks to the MCP endpoint the way a client does, over JSON-RPC.
 *
 * Every tool call is a three step conversation - `initialize`, the `notifications/initialized`
 * acknowledgement, then the call itself - carrying the session id the server handed out. Tests are
 * about what the tools answer, so that dance lives here rather than in each of them.
 */
trait McpJsonRpc {

	/**
	 * Matches `bug_catcher.mcp.access_token` in tests/App/config/packages/bug_catcher.yaml.
	 */
	private const TOKEN = 'test-mcp-token';

	private const PROTOCOL_VERSION = '2025-06-18';

	/**
	 * @param array<string, mixed> $payload
	 *
	 * @return array<string, mixed> zenstruck HttpOptions
	 */
	private function rpcOptions(array $payload, ?string $token = null, ?string $sessionId = null): array {
		$headers = [
			'Content-Type' => 'application/json',
			// the streamable HTTP transport answers either way and refuses a client that accepts
			// neither, so both have to be named
			'Accept'       => 'application/json, text/event-stream',
		];
		if ($token !== null) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		if ($sessionId !== null) {
			$headers['Mcp-Session-Id'] = $sessionId;
		}

		return ['headers' => $headers, 'body' => json_encode($payload, JSON_THROW_ON_ERROR)];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function initializeRequest(): array {
		return [
			'jsonrpc' => '2.0',
			'id'      => 1,
			'method'  => 'initialize',
			'params'  => [
				'protocolVersion' => self::PROTOCOL_VERSION,
				'capabilities'    => [],
				'clientInfo'      => ['name' => 'bug-catcher-tests', 'version' => '1.0'],
			],
		];
	}
}
