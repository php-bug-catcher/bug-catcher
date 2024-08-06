<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 25. 7. 2024
 * Time: 15:03
 */
namespace PhpSentinel\BugCatcher\Tests\Integration\Twig\LogList;

use PhpSentinel\BugCatcher\Tests\App\Factory\RecordLogFactory;
use DateTime;
use PhpSentinel\BugCatcher\Tests\App\KernelTestCase;
use PhpSentinel\BugCatcher\Twig\Components\LogList\RecordLog;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class RecordLogTest extends KernelTestCase {
	use InteractsWithTwigComponents;
	use ResetDatabase;
	use Factories;

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

		$rendered = $this->mountTwigComponent('RecordLog', ['log' => $record->_real(), "status" => "new"]);
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
}
