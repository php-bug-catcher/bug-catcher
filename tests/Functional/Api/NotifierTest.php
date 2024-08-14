<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 8. 2024
 * Time: 14:33
 */
namespace PhpSentinel\BugCatcher\Tests\Functional\Api;

use Doctrine\Common\Collections\ArrayCollection;
use PhpSentinel\BugCatcher\Enum\Importance;
use PhpSentinel\BugCatcher\Enum\NotifyRepeat;
use PhpSentinel\BugCatcher\Tests\App\EventSubscriber\EmailNotifyListener;
use PhpSentinel\BugCatcher\Tests\App\Factory\NotifierEmailFactory;
use PhpSentinel\BugCatcher\Tests\App\Factory\ProjectFactory;
use PhpSentinel\BugCatcher\Tests\App\KernelTestCase;
use PhpSentinel\BugCatcher\Tests\App\Service\ListenerIsCalled;
use PhpSentinel\BugCatcher\Tests\Functional\apiTestHelper;

class NotifierTest extends KernelTestCase {
	use apiTestHelper;

	public function testNotifierPassSimple() {
		$project  = ProjectFactory::createOne([
			"code"    => "testProject",
			"enabled" => true,
		]);
		$notifier = NotifierEmailFactory::createOne([
			"projects"          => new ArrayCollection([$project->_real()]),
			"minimalImportance" => Importance::Low,
			"threshold"         => 1,
			"delayInterval"     => 0,
			"delay"             => NotifyRepeat::None,
			"component"         => "same-error-count",
			"lastOkStatusCount" => 0,
			"firstOkStatus"     => null,
			"lastNotified"      => null,
			"clearInterval"     => 1,
			"repeatAtSkipped"   => 0,
			"repeat"            => NotifyRepeat::FrequencyRecords,
			"clearAt"           => NotifyRepeat::FrequencyRecords,
			"failedStatusCount" => 0,
			"lastFailedStatus"  => null,
		]);

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
					"projectCode" => "testProject",
				]),
			])
			->assertStatus(201);
		/** @var ListenerIsCalled $listenerIsCalled */
		$listenerIsCalled = $this->getContainer()->get(ListenerIsCalled::class);
		self::assertTrue($listenerIsCalled->isCalled(EmailNotifyListener::class));
	}

	public function testNotifierPassWithRepeatInterval() {
		$project  = ProjectFactory::createOne([
			"code"    => "testProject",
			"enabled" => true,
		]);
		$notifier = NotifierEmailFactory::createOne([
			"projects"          => new ArrayCollection([$project->_real()]),
			"minimalImportance" => Importance::Low,
			"threshold"         => 1,
			"delayInterval"     => 0,
			"delay"             => NotifyRepeat::None,
			"component"         => "same-error-count",
			"lastOkStatusCount" => 0,
			"firstOkStatus"     => null,
			"lastNotified"      => null,
			"clearInterval"     => 6,
			"repeatAtSkipped"   => 1,
			"repeatInterval"    => 5,
			"repeat"            => NotifyRepeat::FrequencyRecords,
			"clearAt"           => NotifyRepeat::FrequencyRecords,
			"failedStatusCount" => 0,
			"lastFailedStatus"  => null,
		]);

		[$browser] = $this->browser([]);
		for ($i = 0; $i < 5; $i++) {
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
			/** @var ListenerIsCalled $listenerIsCalled */
			$listenerIsCalled = $this->getContainer()->get(ListenerIsCalled::class);
			if ($i == 4) {
				self::assertTrue($listenerIsCalled->isCalled(EmailNotifyListener::class));
			} else {
				self::assertFalse($listenerIsCalled->isCalled(EmailNotifyListener::class));
			}
		}
	}

	public function testNotifierWithDelay() {
		$project  = ProjectFactory::createOne([
			"code"    => "testProject",
			"enabled" => true,
		]);
		$notifier = NotifierEmailFactory::createOne([
			"projects"          => new ArrayCollection([$project->_real()]),
			"minimalImportance" => Importance::Low,
			"threshold"         => 1,
			"delayInterval"     => 3,
			"delay"             => NotifyRepeat::FrequencyRecords,
			"component"         => "same-error-count",
			"lastOkStatusCount" => 0,
			"firstOkStatus"     => null,
			"lastNotified"      => null,
			"clearInterval"     => 1,
			"repeatAtSkipped"   => 1,
			"repeatInterval"    => 0,
			"repeat"            => NotifyRepeat::FrequencyRecords,
			"clearAt"           => NotifyRepeat::FrequencyRecords,
			"failedStatusCount" => 0,
			"lastFailedStatus"  => null,
		]);

		[$browser] = $this->browser([]);
		for ($i = 0; $i < 5; $i++) {
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
			/** @var ListenerIsCalled $listenerIsCalled */
			$listenerIsCalled = $this->getContainer()->get(ListenerIsCalled::class);
			if ($i >= 3) {
				self::assertTrue($listenerIsCalled->isCalled(EmailNotifyListener::class));
				$listenerIsCalled->clear(EmailNotifyListener::class);
			} else {
				self::assertFalse($listenerIsCalled->isCalled(EmailNotifyListener::class));
			}
		}
	}

	/**
	 * @after
	 */
	public function tearDown(): void {
		$this->getContainer()->get(ListenerIsCalled::class)->clearAll();
	}
}