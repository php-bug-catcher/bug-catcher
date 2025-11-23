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

}
