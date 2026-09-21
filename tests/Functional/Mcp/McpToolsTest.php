<?php

namespace BugCatcher\Tests\Functional\Mcp;

use BugCatcher\Entity\Project;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordCronFactory;
use BugCatcher\Tests\App\Factory\RecordLogTraceFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Functional\apiTestHelper;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Kregel\ExceptionProbe\Codeframe;

/**
 * The tools as a client meets them: over HTTP, through the JSON-RPC handshake. The unit and
 * integration tests call the same methods directly - what these add is the proof that the bundle,
 * the transport and the firewall in between deliver them intact.
 */
class McpToolsTest extends KernelTestCase {
	use apiTestHelper;
	use McpJsonRpc;

	private Project $project;

	public function testTheServerAdvertisesItsTools() {
		[$browser] = $this->browser();
		$session   = $this->openSession($browser);

		$tools = $this->rpc($browser, $session, 'tools/list')['result']['tools'];

		$this->assertEqualsCanonicalizing(
			['list_projects', 'search_records', 'get_record_detail', 'set_record_status'],
			array_column($tools, 'name')
		);
	}

	/**
	 * Every other tool is filtered by a project code, so this one is where a client starts.
	 */
	public function testListProjectsAnswersOverTheTransport() {
		$this->seed();
		[$browser] = $this->browser();
		$session   = $this->openSession($browser);

		$result = $this->callTool($browser, $session, 'list_projects');

		$this->assertFalse($result['isError'], $result['text']);
		$this->assertContains('shop', array_column($result['payload'], 'code'));
	}

	public function testSearchRecordsAnswersOverTheTransport() {
		$this->seed();
		[$browser] = $this->browser();
		$session   = $this->openSession($browser);

		$result = $this->callTool($browser, $session, 'search_records', ['projectCode' => 'shop']);

		$this->assertFalse($result['isError'], $result['text']);
		$this->assertCount(1, $result['payload']);
		$this->assertSame(2, $result['payload'][0]['count']);
		$this->assertSame('Division by zero', $result['payload'][0]['message']);
	}

	public function testTheStackTraceSurvivesTheRoundTrip() {
		$this->seed();
		[$browser] = $this->browser();
		$session   = $this->openSession($browser);

		$id     = $this->callTool($browser, $session, 'search_records', ['projectCode' => 'shop'])['payload'][0]['id'];
		$detail = $this->callTool($browser, $session, 'get_record_detail', ['recordId' => $id]);

		$this->assertFalse($detail['isError'], $detail['text']);
		$this->assertSame(
			"#0 /Cpe/GponRadius.php:58  RuntimeException: boom\n#1 /Runner.php:12  run()",
			$detail['payload']['stackTrace']
		);
	}

	/**
	 * The whole point of the server: the assistant fixed the cause, so the error goes away.
	 */
	public function testAnErrorCanBeResolvedOverTheTransport() {
		$this->seed();
		[$browser] = $this->browser();
		$session   = $this->openSession($browser);

		$id = $this->callTool($browser, $session, 'search_records', ['projectCode' => 'shop'])['payload'][0]['id'];

		$resolved = $this->callTool($browser, $session, 'set_record_status', [
			'recordId' => $id,
			'status'   => 'resolved',
		]);

		$this->assertFalse($resolved['isError'], $resolved['text']);
		$this->assertSame('resolved', $resolved['payload']['status']);
		$this->assertSame('new', $resolved['payload']['previousStatus']);

		self::getContainer()->get(EntityManagerInterface::class)->clear();
		$this->assertSame(
			[],
			$this->callTool($browser, $session, 'search_records', ['projectCode' => 'shop'])['payload']
		);
	}

	/**
	 * A record type the application added, all the way out to the client: it has to arrive with the
	 * type it is and with the fields only that type knows, not flattened into the common shape.
	 */
	public function testACustomRecordTypeSurvivesTheRoundTripWithItsOwnFields() {
		$this->project = ProjectFactory::createOne(["code" => "shop", "enabled" => true])->_real();
		RecordCronFactory::createOne([
			'project'   => $this->project,
			'status'    => 'new',
			'command'   => 'app:import',
			'date'      => new DateTimeImmutable('-1 hour'),
			'lastStart' => new DateTimeImmutable('-1 hour'),
			'lastEnd'   => null,
		]);
		[$browser] = $this->browser();
		$session   = $this->openSession($browser);

		$found = $this->callTool($browser, $session, 'search_records', [
			'projectCode' => 'shop',
			'type'        => 'cron',
		]);

		$this->assertFalse($found['isError'], $found['text']);
		$this->assertSame('cron', $found['payload'][0]['type']);

		$detail = $this->callTool($browser, $session, 'get_record_detail', [
			'recordId' => $found['payload'][0]['id'],
		]);

		$this->assertFalse($detail['isError'], $detail['text']);
		$this->assertSame('app:import', $detail['payload']['details']['command']);
		$this->assertNull($detail['payload']['details']['lastEnd']);
	}

	/**
	 * A tool that refuses has to say why in a way the client can read back, rather than failing the
	 * whole JSON-RPC call.
	 */
	public function testARefusalReachesTheClientAsAReadableMessage() {
		[$browser] = $this->browser();
		$session   = $this->openSession($browser);

		$result = $this->callTool($browser, $session, 'search_records', ['projectCode' => 'nosuch']);

		$this->assertTrue($result['isError']);
		$this->assertStringContainsString('nosuch', $result['text']);
		$this->assertStringContainsString('list_projects', $result['text']);
	}

	/**
	 * The status whitelist is declared in the tool's schema, so the transport turns a bad value
	 * away before the tool runs at all.
	 */
	public function testTheTransportRejectsAStatusOutsideTheSchema() {
		$this->seed();
		[$browser] = $this->browser();
		$session   = $this->openSession($browser);

		$id = $this->callTool($browser, $session, 'search_records', ['projectCode' => 'shop'])['payload'][0]['id'];

		$response = $this->rpc($browser, $session, 'tools/call', [
			'name'      => 'set_record_status',
			'arguments' => ['recordId' => $id, 'status' => 'deleted'],
		]);

		$this->assertArrayHasKey('error', $response);
		$this->assertStringContainsString('status', $response['error']['message']);
	}

	/**
	 * Both occurrences are of the same record type on purpose. A client reports one error the same
	 * way every time, and mixing the types here would run into a limitation that has nothing to do
	 * with the transport: the status update is dispatched to the repository of the record's own
	 * class, so a group holding both a RecordLog and a RecordLogTrace is only half cleared. The
	 * dashboard's own "fix it" button behaves the same way.
	 */
	private function seed(): void {
		$this->project = ProjectFactory::createOne(["code" => "shop", "enabled" => true])->_real();

		RecordLogTraceFactory::createOne($this->attributes([
			'date'       => new DateTimeImmutable('2026-01-01 10:00:00'),
			'message'    => 'Division by zero',
			'stackTrace' => null,
		]));
		RecordLogTraceFactory::createOne($this->attributes([
			'date'       => new DateTimeImmutable('2026-01-03 10:00:00'),
			'message'    => 'Division by zero',
			'stackTrace' => serialize([
				new Codeframe('/app/src/Cpe/GponRadius.php', 58, [], 'RuntimeException: boom'),
				new Codeframe('/app/src/Runner.php', 12, [], 'run()'),
			]),
		]));
	}

	/**
	 * @param array<string, mixed> $attributes
	 *
	 * @return array<string, mixed>
	 */
	private function attributes(array $attributes): array {
		return [
			'hash'       => 'shared-hash',
			'status'     => 'new',
			'level'      => 500,
			'code'       => 'E42',
			'requestUri' => '/checkout',
			'project'    => $this->project,
			...$attributes,
		];
	}
}
