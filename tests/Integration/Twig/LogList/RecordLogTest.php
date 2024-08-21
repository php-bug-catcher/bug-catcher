<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 7. 2024
 * Time: 15:03
 */
namespace BugCatcher\Tests\Integration\Twig\LogList;

use BugCatcher\Tests\App\Factory\RecordLogFactory;
use DateTime;
use BugCatcher\Tests\App\Factory\RecordLogTraceFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Integration\Trait\GetStackTrace;
use BugCatcher\Twig\Components\LogList\RecordLog;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class RecordLogTest extends KernelTestCase {
	use InteractsWithTwigComponents;
	use ResetDatabase;
	use Factories;
	use GetStackTrace;

	public function testClearOne() {

		RecordLogFactory::createMany(10, [
			"status" => "new",
			"hash"   => "hash-2",
			"date"   => new DateTime("2022-01-01 00:00:00"),
		]);

		$record = RecordLogFactory::createMany(10, [
			"status" => "new",
			"hash"   => "hash",
			"date"   => new DateTime("2022-01-01 00:00:00"),
		])[0];

		$rendered = $this->mountTwigComponent('LogList:RecordLog', ['log' => $record->_real(), "status" => "new"]);
		$this->assertInstanceOf(RecordLog::class, $rendered);

		$rendered->clearOne("resolved");

		$count = RecordLogFactory::count([
			"hash"   => "hash",
			"status" => "new",
		]);
		$this->assertEquals(0, $count);

		$count = RecordLogFactory::count([
			"hash"   => "hash-2",
			"status" => "new",
		]);
		$this->assertEquals(10, $count);

	}

	public function testClearStackTrace() {
		$record = RecordLogTraceFactory::createOne([
			"stackTrace" => $this->getStackTrace(),
			"status"     => "new",
			"hash"       => "hash",
		]);

		$rendered = $this->mountTwigComponent('LogList:RecordLog', ['log' => $record->_real(), "status" => "new"]);
		$this->assertInstanceOf(RecordLog::class, $rendered);

		$rendered->clearOne("resolved");
		$record->_refresh();
		$this->assertNull($record->getStackTrace());

	}
}
