<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Functional\Controller;

use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordLogFactory;
use BugCatcher\Tests\App\Factory\RecordLogTraceFactory;
use BugCatcher\Tests\App\Factory\UserFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Functional\apiTestHelper;
use DateTimeImmutable;

/**
 * The dashboard with something on it.
 *
 * Everything else here renders an empty list, and an empty list skips most of LogList: the two
 * worst bugs of 2.0.0 - a route the installation never imported and a selection transformer that
 * cannot name a Record - only happened once the list had a row in it, and shipped because nothing
 * asserted on one.
 */
class DashboardListTest extends KernelTestCase
{
	use apiTestHelper;

	public function testTheListRendersTheRecordsOfTheUsersProjects(): void
	{
		$project = ProjectFactory::createOne(['code' => 'listed', 'enabled' => true])->_real();
		$user    = UserFactory::createOne([
			'email'    => 'lists@example.com',
			'enabled'  => true,
			'roles'    => ['ROLE_DEVELOPER'],
			'projects' => [$project],
		])->_real();

		RecordLogFactory::createOne([
			'project' => $project,
			'message' => 'a plain log on the dashboard',
			'status'  => 'new',
			'hash'    => 'hash-plain',
			'date'    => new DateTimeImmutable('-1 hour'),
		]);
		RecordLogTraceFactory::createOne([
			'project'    => $project,
			'message'    => 'a trace log on the dashboard',
			'status'     => 'new',
			'hash'       => 'hash-trace',
			'date'       => new DateTimeImmutable('-2 hours'),
			'stackTrace' => "#0 /app/src/Foo.php(13): bar()",
		]);

		[$browser] = $this->browser();

		$browser
			->actingAs($user)
			->visit('/')
			->assertStatus(200)
			->assertSeeIn('body', 'a plain log on the dashboard')
			->assertSeeIn('body', 'a trace log on the dashboard')
			// the "select all" button, whose URL comes from the persistent-state routes an
			// installation has to import
			->assertContains('_persistent-state-selection/toggle');
	}
}
