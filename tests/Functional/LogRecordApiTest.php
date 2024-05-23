<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 23. 5. 2024
 * Time: 10:27
 */
namespace App\Tests\Functional;

use App\Factory\ProjectFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LogRecordApiTest extends KernelTestCase {
	use ApiTestHelper;

	public function testCreateLog() {
		$project = ProjectFactory::createOne();
		$log     = [
			"message"     => "Exception:foo",
			"requestUri"  => "http://app.wip/request",
			"level"       => 5,
			"projectCode" => $project->getCode(),
		];
		$this->browser()
			->post('/api/log_records', [
				"headers" => [
					"Content-Type" => "application/json",
				],
				"body"    => json_encode($log),
			])
			->assertStatus(201);
	}
}