<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 24. 9. 2024
 * Time: 20:45
 */

namespace BugCatcher\Tests\Functional\Service;

use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordLogFactory;
use BugCatcher\Tests\App\Factory\RecordLogWithholderFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Functional\apiTestHelper;

class RecordLogWithholderTest extends KernelTestCase
{
    use apiTestHelper;

    public function testNotMatchRegex(): void
    {
        $project = ProjectFactory::createOne([
            "code" => "testProject",
            "enabled" => true,
        ]);
        $withholder = RecordLogWithholderFactory::createOne([
            "project" => $project->_real(),
            "regex" => "/^error/",
            "threshold" => 10,
            "thresholdInterval" => 60,
        ]);
        [$browser] = $this->browser([]);
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
                ]),
            ])
            ->assertStatus(201);

        $count = RecordLogFactory::count([
            "project" => $project->_real(),
            "status" => "new",
        ]);
        $this->assertEquals(1, $count);
    }

    public function testMatchRegex(): void
    {
        $project = ProjectFactory::createOne([
            "code" => "testProject",
            "enabled" => true,
        ]);
        $withholder = RecordLogWithholderFactory::createOne([
            "project" => $project->_real(),
            "regex" => "/^message/",
            "threshold" => 10,
            "thresholdInterval" => 60,
        ]);
        [$browser] = $this->browser([]);
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
                ]),
            ])
            ->assertStatus(201);

        $count = RecordLogFactory::count([
            "project" => $project->_real(),
            "status" => "new",
        ]);
        $this->assertEquals(0, $count);
    }

    public function testMatchThresholdInterval(): void
    {
        $project = ProjectFactory::createOne([
            "code" => "testProject",
            "enabled" => true,
        ]);
        $withholder = RecordLogWithholderFactory::createOne([
            "project" => $project->_real(),
            "regex" => "/^message/",
            "threshold" => 11,
            "thresholdInterval" => 60,
        ]);
        [$browser] = $this->browser([]);
        for ($i = 0; $i < 10; $i++) {
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
                    ]),
                ])
                ->assertStatus(201);
        }

        $count = RecordLogFactory::count([
            "project" => $project->_real(),
            "status" => "new",
        ]);
        $this->assertEquals(0, $count);
    }

    public function testExceedThreshold(): void
    {
        $project = ProjectFactory::createOne([
            "code" => "testProject",
            "enabled" => true,
        ]);
        $withholder = RecordLogWithholderFactory::createOne([
            "project" => $project->_real(),
            "regex" => "/^message/",
            "threshold" => 10,
            "thresholdInterval" => 60,
        ]);
        [$browser] = $this->browser([]);
        for ($i = 0; $i < 10; $i++) {
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
                    ]),
                ])
                ->assertStatus(201);
        }

        $count = RecordLogFactory::count([
            "project" => $project->_real(),
            "status" => "new",
        ]);
        $this->assertEquals(10, $count);
    }

    public function testExceedThresholdInterval(): void
    {
        $project = ProjectFactory::createOne([
            "code" => "testProject",
            "enabled" => true,
        ]);
        $withholder = RecordLogWithholderFactory::createOne([
            "project" => $project->_real(),
            "regex" => "/^message/",
            "threshold" => 10,
            "thresholdInterval" => -1,
        ]);
        [$browser] = $this->browser([]);
        for ($i = 0; $i < 3; $i++) {
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
                    ]),
                ])
                ->assertStatus(201);
        }

        $count = RecordLogFactory::count([
            "project" => $project->_real(),
            "status" => "resolved-{$withholder->getId()}",
        ]);
        $this->assertEquals(3, $count);
    }

}