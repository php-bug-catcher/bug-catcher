<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 6. 8. 2024
 * Time: 15:11
 */
namespace BugCatcher\Tests\Functional\Api;

use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Functional\apiTestHelper;

class SendRecordTest extends KernelTestCase {
	use apiTestHelper;

	public function testSendPlainRecord(): void {
		[$browser] = $this->browser([]);

		ProjectFactory::createOne([
			"code" => "testProject",
		]);

		$browser
			->post("/api/record_logs", [
				"headers" => [
					"Content-Type" => "application/json",
				],
				"body"    => json_encode([
					"level"       => 500,
					"message"     => "message",
					"requestUri"  => "/",
					"projectCode" => "testProject",
				]),
			])
			->assertStatus(201);
	}

	public function testProjectNotFound(): void {
		[$browser] = $this->browser([]);
		$browser
			->post("/api/record_logs", [
				"headers" => [
					"Content-Type" => "application/json",
				],
				"body"    => json_encode([
					"level"       => 500,
					"message"     => "message",
					"requestUri"  => "/",
					"projectCode" => "notFound",
				]),
			])
			->assertStatus(404);
	}

    public function testMetadata(): void
    {
        [$browser] = $this->browser([]);

        ProjectFactory::createOne([
            "code" => "testProject",
        ]);
        $browser
            ->post("/api/record_logs", [
                "headers" => [
                    "Content-Type" => "application/json",
                ],
                "body" => json_encode([
                    "level" => 500,
                    "message" => "message",
                    "requestUri" => "/",
                    "projectCode" => "testProject",
                    "metadata" => [
                        "key" => "value",
                        "key2" => "value2",
                    ],
                ]),
            ])
            ->assertStatus(201);
    }
}