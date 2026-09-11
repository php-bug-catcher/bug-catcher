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
	 * JSON-RPC ids have to differ within a session, and a test may send several messages.
	 */
	private static int $nextId = 1;

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
	 * Walks the handshake and returns the session id the server minted for it.
	 */
	private function openSession(KernelBrowser $browser): string {
		$browser->post('/mcp', $this->rpcOptions($this->initializeRequest(), self::TOKEN))
			->assertSuccessful();

		$sessionId = $browser->client()->getResponse()->headers->get('Mcp-Session-Id');
		$this->assertNotNull($sessionId, 'The server did not hand out a session id.');

		$browser->post('/mcp', $this->rpcOptions(
			['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
			self::TOKEN,
			$sessionId,
		));

		return $sessionId;
	}

	/**
	 * @param array<string, mixed> $arguments
	 *
	 * @return array<string, mixed> the JSON-RPC response, result or error
	 */
	private function rpc(KernelBrowser $browser, string $sessionId, string $method, array $arguments = []): array {
		$browser->post('/mcp', $this->rpcOptions([
			'jsonrpc' => '2.0',
			'id'      => self::$nextId++,
			'method'  => $method,
			'params'  => $arguments,
		], self::TOKEN, $sessionId));

		return $this->decode($browser->client()->getResponse()->getContent());
	}

	/**
	 * The payload a tool answered with, already decoded.
	 *
	 * Tools here return arrays, which the SDK renders as one JSON encoded text content.
	 *
	 * @param array<string, mixed> $arguments
	 *
	 * @return array{isError: bool, payload: mixed, text: string}
	 */
	private function callTool(KernelBrowser $browser, string $sessionId, string $name, array $arguments = []): array {
		$response = $this->rpc($browser, $sessionId, 'tools/call', ['name' => $name, 'arguments' => $arguments]);

		$this->assertArrayNotHasKey('error', $response, 'The call was rejected before it reached the tool: '
			. json_encode($response['error'] ?? null));

		$text = $response['result']['content'][0]['text'] ?? '';

		return [
			'isError' => (bool)($response['result']['isError'] ?? false),
			'text'    => $text,
			'payload' => json_decode($text, true),
		];
	}

	/**
	 * The transport may answer as plain JSON or as a single server-sent event, depending on what
	 * the client accepts; both carry the same JSON-RPC message.
	 *
	 * @return array<string, mixed>
	 */
	private function decode(string $body): array {
		if (str_starts_with(ltrim($body), '{')) {
			return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
		}

		foreach (explode("\n", $body) as $line) {
			if (str_starts_with($line, 'data:')) {
				return json_decode(trim(substr($line, 5)), true, flags: JSON_THROW_ON_ERROR);
			}
		}

		self::fail('Could not read a JSON-RPC message out of: ' . substr($body, 0, 500));
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
