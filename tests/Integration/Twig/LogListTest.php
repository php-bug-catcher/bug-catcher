<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 7. 2024
 * Time: 16:03
 */
namespace PhpSentinel\BugCatcher\Tests\Integration\Twig;

use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use PhpSentinel\BugCatcher\Entity\Record;
use PhpSentinel\BugCatcher\Factory\ProjectFactory;
use PhpSentinel\BugCatcher\Factory\RecordLogFactory;
use PhpSentinel\BugCatcher\Factory\RecordLogTraceFactory;
use PhpSentinel\BugCatcher\Factory\RecordPingFactory;
use PhpSentinel\BugCatcher\Factory\UserFactory;
use PhpSentinel\BugCatcher\Tests\Integration\KernelTestCase;
use PhpSentinel\BugCatcher\Twig\Components\LogList;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LogListTest extends KernelTestCase {
	use InteractsWithTwigComponents;
	use ResetDatabase;
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
		$logs = $rendered->getLogs();
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
		$logs = $rendered->getLogs();
		$this->assertCount(1, $logs);
		foreach ($logs as $log) {
			$this->assertSame(100, $log->getCount());
		}
	}

	public function testClearAll() {
		RecordLogFactory::createMany(15, [
			"date"   => new DateTime("2022-01-01 00:00:00"),
			"status" => "new",
		]);
		RecordLogFactory::createMany(10, [
			"date"   => new DateTime("2022-02-01 00:00:00"),
			"status" => "new",
		]);
		$this->assertSame(25, RecordLogFactory::count());

		$rendered = $this->mountTwigComponent('LogList', ["status" => "new"]);
		$this->assertInstanceOf(LogList::class, $rendered);
		$rendered->clearAll(new DateTimeImmutable("2022-01-01 01:00:00"));

		$this->assertSame(10, RecordLogFactory::count(["status" => "new"]));
		$this->assertSame(15, RecordLogFactory::count(["status" => "resolved"]));
	}
}
