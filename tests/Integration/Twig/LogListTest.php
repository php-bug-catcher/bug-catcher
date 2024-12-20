<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 7. 2024
 * Time: 16:03
 */
namespace BugCatcher\Tests\Integration\Twig;

use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordLogFactory;
use BugCatcher\Tests\App\Factory\RecordLogTraceFactory;
use BugCatcher\Tests\App\Factory\RecordPingFactory;
use BugCatcher\Tests\App\Factory\UserFactory;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Twig\Components\LogList;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LogListTest extends KernelTestCase {
	use InteractsWithTwigComponents;

    //use ResetDatabase;
	use Factories;


	function testLogsCount() {
		$user = UserFactory::createOne([
		]);
		ProjectFactory::createMany(3, [
			"users"   => new ArrayCollection([$user->_real()]),
			"enabled" => true,
		]);
		$user->_refresh();
		$this->loginUser($user->_real());

		foreach ([
					 RecordLogFactory::createMany(...),
					 RecordLogTraceFactory::createMany(...),
					 RecordPingFactory::createMany(...),
				 ] as $pos => $createMany) {
			for ($i = 0; $i < 3; $i++) {
				$createMany(10, [
					"hash"    => "hash-{$pos}-{$i}",
					"status"  => "new",
					"project" => ProjectFactory::random(),
				]);
			}
		}
		$rendered = $this->mountTwigComponent('LogList', ["status" => "new"]);
		$this->assertInstanceOf(LogList::class, $rendered);
        $rendered->init();
        $logs = $rendered->logs;
		$this->assertCount(6, $logs);
		foreach ($logs as $log) {
			$this->assertSame(10, $log->getCount());
		}
	}

	public function testMaxRecords() {
		$user = UserFactory::createOne([
		]);
		ProjectFactory::createMany(3, [
			"users"   => new ArrayCollection([$user->_real()]),
			"enabled" => true,
		]);
		$user->_refresh();
		$this->loginUser($user->_real());

		RecordLogFactory::createMany(150, [
			"hash"    => "hash",
			"status"  => "new",
			"project" => ProjectFactory::random(),
		]);
		$rendered = $this->mountTwigComponent('LogList', ["status" => "new"]);
		$this->assertInstanceOf(LogList::class, $rendered);
        $rendered->init();
        $logs = $rendered->logs;
		$this->assertCount(1, $logs);
		foreach ($logs as $log) {
			$this->assertSame(100, $log->getCount());
		}
	}

	public function testClearAll() {
		$user = UserFactory::createOne([
		]);
		ProjectFactory::createMany(3, [
			"users"   => new ArrayCollection([$user->_real()]),
			"enabled" => true,
		]);
		$user->_refresh();
		$this->loginUser($user->_real());
		RecordLogFactory::createMany(15, [
            "date" => new DateTime("2022-01-02 00:00:00"),
			"status" => "new",
			"project" => ProjectFactory::random(),
		]);
		RecordLogFactory::createMany(5, [
            "date" => new DateTime("2022-01-02 00:00:00"),
			"status" => "status-to-not-to-be-deleted",
			"project" => ProjectFactory::random(),
		]);
		RecordLogTraceFactory::createMany(15, [
            "date" => new DateTime("2022-01-02 00:10:00"),
			"status" => "new",
			"project" => ProjectFactory::random(),
		]);
		RecordLogFactory::createMany(10, [
            "date" => new DateTime("2022-02-02 00:00:00"),
			"status" => "new",
			"project" => ProjectFactory::random(),
		]);
		$this->assertSame(45, RecordLogFactory::count());

		$rendered = $this->mountTwigComponent('LogList', ["status" => "new"]);
		$this->assertInstanceOf(LogList::class, $rendered);
        $rendered->clearAll(new DateTimeImmutable("2022-01-01 01:00:00"), new DateTimeImmutable("2022-01-02 01:00:00"));

		$this->assertSame(10, RecordLogFactory::count(["status" => "new"]));
		$this->assertSame(5, RecordLogFactory::count(["status" => "status-to-not-to-be-deleted"]));
		$this->assertSame(30, RecordLogFactory::count(["status" => "resolved"]));
		$this->assertSame(15, RecordLogTraceFactory::count(["status" => "resolved"]));
	}

    public function testClearAllMax()
    {
        $user = UserFactory::createOne([
        ]);
        ProjectFactory::createMany(3, [
            "users" => new ArrayCollection([$user->_real()]),
            "enabled" => true,
        ]);
        $user->_refresh();
        $this->loginUser($user->_real());
        RecordLogFactory::createMany(50, [
            "date" => new DateTime("2022-01-01 00:00:00"),
            "status" => "new",
            "project" => ProjectFactory::random(),
        ]);
        RecordLogFactory::createMany(100, [
            "date" => new DateTime("2022-01-02 00:00:00"),
            "status" => "new",
            "project" => ProjectFactory::random(),
        ]);
        $this->assertSame(150, RecordLogFactory::count());

        $rendered = $this->mountTwigComponent('LogList', ["status" => "new"]);
        $rendered->init();
        $this->assertInstanceOf(LogList::class, $rendered);
        $rendered->clearAll($rendered->from, $rendered->to);

        $this->assertSame(50, RecordLogFactory::count(["status" => "new"]));
        $this->assertSame(100, RecordLogFactory::count(["status" => "resolved"]));
    }
}
