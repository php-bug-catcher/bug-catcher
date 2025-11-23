<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 19. 8. 2024
 * Time: 13:06
 */
namespace BugCatcher\Tests\Functional\Api;

use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Functional\apiTestHelper;
use DateTime;
use DateTimeImmutable;

class CronRecordTest extends KernelTestCase {
	use apiTestHelper;

	public function testSendPlainRecord(): void {
		[$browser] = $this->browser([]);

		ProjectFactory::createOne([
			"code" => "testProject",
		]);

		$browser
			->post("/api/record_cron", [
				"headers" => [
					"Content-Type" => "application/json",
				],
				"body"    => json_encode([
					"level"       => 500,
					"command"     => "app:test-cron",
					"lastStart" => (new DateTimeImmutable("2022-01-01 10:00:00"))->format(DateTime::RFC3339_EXTENDED),
					"lastEnd"   => (new DateTimeImmutable("2022-01-01 10:00:01"))->format(DateTime::RFC3339_EXTENDED),
					"interval"    => 360,
					"estimated"   => 10,
					"projectCode" => "testProject",
				]),
			])
			->assertStatus(201);
	}

	public function testSendPlainRecordWithCode(): void {
		[$browser] = $this->browser([]);

		ProjectFactory::createOne([
			"code" => "testProject",
		]);

		$browser
			->post("/api/record_cron", [
				"headers" => [
					"Content-Type" => "application/json",
				],
				"body"    => json_encode([
					"level"     => 500,
					"command"   => "app:test-cron",
					"code"      => "testCode",
					"lastStart" => (new DateTimeImmutable("2022-01-01 10:00:00"))->format(DateTime::RFC3339_EXTENDED),
					"lastEnd"   => (new DateTimeImmutable("2022-01-01 10:00:01"))->format(DateTime::RFC3339_EXTENDED),
					"interval"  => 360,
					"estimated" => 10,
					"projectCode" => "testProject",
				]),
			])
			->assertStatus(201);
	}
}