<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 7. 2024
 * Time: 16:03
 */

namespace BugCatcher\Tests\Functional\Twig;

use BugCatcher\Entity\NotifierFavicon;
use BugCatcher\Enum\Importance;
use BugCatcher\Enum\NotifyRepeat;
use BugCatcher\Service\DashboardImportance;
use BugCatcher\Tests\App\Factory\NotifierFaviconFactory;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\UserFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Functional\apiTestHelper;
use Doctrine\Common\Collections\ArrayCollection;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class FaviconTest extends KernelTestCase
{
    use ResetDatabase;
    use apiTestHelper;
    use Factories;

    const THRESHOLD = 25;

    function testOnlyMineProjects(): void
    {

        $meUser = UserFactory::createOne([]);
        $otherUser = UserFactory::createOne([]);
        $this->loginUser($meUser->_real());
        $max = count(Importance::all()) - 1;
        $logsCount = (int)(2 * self::THRESHOLD * $max / self::THRESHOLD);
        $otherProject = ProjectFactory::createOne([
            "code" => "testProject",
            "enabled" => true,
            "users" => new ArrayCollection([$otherUser->_real()])
        ]);
        $myProject = ProjectFactory::createOne([
            "code" => "testProject",
            "enabled" => true,
            "users" => new ArrayCollection([$meUser->_real()])
        ]);
        $notifier = NotifierFaviconFactory::createOne([
            "projects" => new ArrayCollection([$otherProject->_real()]),
            "minimalImportance" => Importance::Low,
            "threshold" => self::THRESHOLD,
            "delayInterval" => 0,
            "delay" => NotifyRepeat::None,
            "component" => "same-error-count",
            "lastOkStatusCount" => 0,
            "firstOkStatus" => null,
            "lastNotified" => null,
            "clearInterval" => 1,
            "repeatAtSkipped" => 0,
            "repeat" => NotifyRepeat::FrequencyRecords,
            "repeatInterval" => 1,
            "clearAt" => NotifyRepeat::None,
            "failedStatusCount" => 0,
            "lastFailedStatus" => null,
        ]);
        [$browser] = $this->browser([]);
        for ($i = 0; $i < $logsCount; $i++) {
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
        /** @var DashboardImportance $importance = */
        $importance = $this->getContainer()->get(DashboardImportance::class);
        /** @var NotifierFavicon $notifier */
        $importances = $importance->load(NotifierFavicon::class, $otherProject->_real());
        [$importance, $notifier] = array_values($importances[$otherProject->getId()->toString()]);
        $this->assertSame(Importance::Medium->value, $importance?->value);
        $this->assertArrayNotHasKey($myProject->getId()->toString(), $importances);
        $this->getContainer()->reset();
    }

    /**
     * @dataProvider logsProvider
     */
    function test(int $logsCount, ?Importance $targetImportance)
    {
        $project = ProjectFactory::createOne([
            "code" => "testProject",
            "enabled" => true,
        ]);
        $notifier = NotifierFaviconFactory::createOne([
            "projects" => new ArrayCollection([$project->_real()]),
            "minimalImportance" => Importance::Low,
            "threshold" => self::THRESHOLD,
            "delayInterval" => 0,
            "delay" => NotifyRepeat::None,
            "component" => "same-error-count",
            "lastOkStatusCount" => 0,
            "firstOkStatus" => null,
            "lastNotified" => null,
            "clearInterval" => 1,
            "repeatAtSkipped" => 0,
            "repeat" => NotifyRepeat::FrequencyRecords,
            "repeatInterval" => 1,
            "clearAt" => NotifyRepeat::None,
            "failedStatusCount" => 0,
            "lastFailedStatus" => null,
        ]);
        [$browser] = $this->browser([]);
        for ($i = 0; $i < $logsCount; $i++) {
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
        /** @var DashboardImportance $importance = */
        $importance = $this->getContainer()->get(DashboardImportance::class);
        /** @var NotifierFavicon $notifier */
        [$importance, $notifier] = array_values($importance->load(NotifierFavicon::class,
            $project->_real())[$project->getId()->toString()]);
        $targetImportance = $targetImportance == Importance::Normal ? null : $targetImportance;
        $this->assertSame($targetImportance?->value, $importance?->value);
        $this->getContainer()->reset();
    }

    public function logsProvider(): iterable
    {
        $max = count(Importance::all()) - 1;
        $ratio = $max / self::THRESHOLD;
        foreach (Importance::all() as $pos => $importance) {
            yield [(int)($pos * self::THRESHOLD * $ratio), $importance];
        }

    }
}
