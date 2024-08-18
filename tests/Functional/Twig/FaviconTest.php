<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 7. 2024
 * Time: 16:03
 */
namespace PhpSentinel\BugCatcher\Tests\Functional\Twig;

use Doctrine\Common\Collections\ArrayCollection;
use PhpSentinel\BugCatcher\Entity\NotifierFavicon;
use PhpSentinel\BugCatcher\Enum\Importance;
use PhpSentinel\BugCatcher\Enum\NotifyRepeat;
use PhpSentinel\BugCatcher\Service\DashboardImportance;
use PhpSentinel\BugCatcher\Tests\App\Factory\NotifierFaviconFactory;
use PhpSentinel\BugCatcher\Tests\App\Factory\ProjectFactory;
use PhpSentinel\BugCatcher\Tests\App\KernelTestCase;
use PhpSentinel\BugCatcher\Tests\Functional\apiTestHelper;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class FaviconTest extends KernelTestCase {
	use ResetDatabase;
	use apiTestHelper;
	use Factories;

	const THRESHOLD = 25;

	/**
	 * @dataProvider logsProvider
	 */
	function test(int $logsCount, ?Importance $targetImportance) {
		$project  = ProjectFactory::createOne([
			"code"    => "testProject",
			"enabled" => true,
		]);
		$notifier = NotifierFaviconFactory::createOne([
			"projects"          => new ArrayCollection([$project->_real()]),
			"minimalImportance" => Importance::Low,
			"threshold"         => self::THRESHOLD,
			"delayInterval"     => 0,
			"delay"             => NotifyRepeat::None,
			"component"         => "same-error-count",
			"lastOkStatusCount" => 0,
			"firstOkStatus"     => null,
			"lastNotified"      => null,
			"clearInterval"     => 1,
			"repeatAtSkipped"   => 0,
			"repeat"            => NotifyRepeat::FrequencyRecords,
			"repeatInterval"    => 1,
			"clearAt"           => NotifyRepeat::None,
			"failedStatusCount" => 0,
			"lastFailedStatus"  => null,
		]);
		[$browser] = $this->browser([]);
		for ($i = 0; $i < $logsCount; $i++) {
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
		/** @var DashboardImportance $importance = */
		$importance = $this->getContainer()->get(DashboardImportance::class);
		/** @var NotifierFavicon $notifier */
		[$importance, $notifier] = array_values($importance->load(NotifierFavicon::class));
		$targetImportance = $targetImportance == Importance::Normal ? null : $targetImportance;
		$this->assertSame($targetImportance?->value, $importance?->value);
		$this->getContainer()->reset();
	}

	public function logsProvider(): iterable {
		$max   = count(Importance::all()) - 1;
		$ratio = $max / self::THRESHOLD;
		foreach (Importance::all() as $pos => $importance) {
			yield [(int)($pos * self::THRESHOLD * $ratio), $importance];
		}

	}
}
