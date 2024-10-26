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
use DateTimeImmutable;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class RecordLogTest extends KernelTestCase {
	use InteractsWithTwigComponents;

    //use ResetDatabase;
	use Factories;
	use GetStackTrace;

	public function testClearOne() {

        $startDate = new DateTime("2022-01-01 00:00:00");
        RecordLogFactory::createMany(10, [
			"status" => "new",
			"hash"   => "hash-2",
            "date" => $startDate,
		]);

		$record = RecordLogFactory::createMany(10, [
			"status" => "new",
			"hash"   => "hash",
            "date" => $startDate,
		])[0];

		$rendered = $this->mountTwigComponent('LogList:RecordLog', ['log' => $record->_real(), "status" => "new"]);
		$this->assertInstanceOf(RecordLog::class, $rendered);

        $rendered->clearOne("resolved", $startDate);

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

    function testClearOneStack(): void
    {
        $startDate = new DateTimeImmutable("2022-01-01 00:00:00");

        for ($i = 1; $i <= 10; $i++) {
            RecordLogFactory::createOne([
                "status" => "new",
                "hash" => "hash",
                "date" => $startDate->modify("+{$i} day"),
            ]);
        }
        $lastDate = $startDate->modify("+11 day");
        $record = RecordLogFactory::createOne([
            "status" => "new",
            "hash" => "hash",
            "date" => $lastDate,
        ]);

        $rendered = $this->mountTwigComponent('LogList:RecordLog', ['log' => $record->_real(), "status" => "new"]);
        $this->assertInstanceOf(RecordLog::class, $rendered);

        $count = RecordLogFactory::count([
            "hash" => "hash",
            "status" => "new",
        ]);
        $this->assertEquals(11, $count);

        $rendered->clearOne("resolved", $startDate);

        $count = RecordLogFactory::count([
            "hash" => "hash",
            "status" => "new",
        ]);
        $this->assertEquals(0, $count);
    }

	public function testClearStackTrace() {
        $startDate = new DateTimeImmutable("2022-01-01 00:00:00");
		$record = RecordLogTraceFactory::createOne([
			"stackTrace" => $this->getStackTrace(),
			"status"     => "new",
			"hash"       => "hash",
            "date" => $startDate,
		]);

		$rendered = $this->mountTwigComponent('LogList:RecordLog', ['log' => $record->_real(), "status" => "new"]);
		$this->assertInstanceOf(RecordLog::class, $rendered);

        $rendered->clearOne("resolved", $startDate);
		$record->_refresh();
		$this->assertNull($record->getStackTrace());

	}
}
