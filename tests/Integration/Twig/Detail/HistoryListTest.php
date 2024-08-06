<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 1. 6. 2024
 * Time: 7:11
 */
namespace PhpSentinel\BugCatcher\Tests\Integration\Twig\Detail;

use App\Factory\RecordLogFactory;
use PhpSentinel\BugCatcher\Tests\App\KernelTestCase;
use PhpSentinel\BugCatcher\Twig\Components\Detail\HistoryList;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class HistoryListTest extends KernelTestCase {
	use InteractsWithTwigComponents;
	use ResetDatabase;
	use Factories;


	public function testZeroHistory() {
		$record   = RecordLogFactory::createOne([
			"hash"   => "hash",
			"status" => "status",
		]);
		$rendered = $this->mountTwigComponent('HistoryList', ['record' => $record]);
		$this->assertInstanceOf(HistoryList::class, $rendered);

		$history = $rendered->getHistory();
		$this->assertCount(1, $history);

		RecordLogFactory::createMany(5, [
			"hash"   => "hash",
			"status" => "status",
		]);
		$history = $rendered->getHistory();
		$this->assertCount(6, $history);
	}

	public function testRenderOne() {
		$date     = new \DateTime("2024-06-01 07:11:00");
		$record   = RecordLogFactory::createOne([
			"hash"   => "hash",
			"status" => "status",
			'date'   => $date,
		]);
		$rendered = $this->renderTwigComponent('HistoryList', ['record' => $record]);
		$this->assertSame($date->format("d.m.Y H:i:s"), $rendered->crawler()->filter('h3')->text());
	}

	public function testRenderMulti() {
		$date      = new \DateTime("2024-06-01 07:11:00");
		$firstDate = clone $date;
		for ($i = 0; $i < 5; $i++) {
			$date->modify("+1 day");
			$record = RecordLogFactory::createOne([
				"hash"   => "hash",
				"status" => "status",
				'date'   => clone $date,
			]);
		}
		$rendered = $this->mountTwigComponent('HistoryList', ['record' => $record]);
		$this->assertInstanceOf(HistoryList::class, $rendered);

		$count = $rendered->getHistory();
		$this->assertCount(5, $count);


		$rendered = $this->renderTwigComponent('HistoryList', ['record' => $record]);

		$firstDate->modify("+1 day");
		$expected = $date->format("d.m.Y H:i:s") . " - " . $firstDate->format("d.m.Y H:i:s");
		$this->assertSame($expected, $rendered->crawler()->filter('button.accordion-button>span')->text());
	}

	public function testRenderMultiCount() {
		RecordLogFactory::createOne([
			"hash"   => "hash",
			"status" => "status",
			'date'   => clone new \DateTime("2024-06-02 07:11:00"),
		]);
		$record   = RecordLogFactory::createMany(5, [
			"hash"   => "hash",
			"status" => "status",
			'date'   => clone new \DateTime("2024-06-01 07:11:00"),
		])[0];
		$rendered = $this->renderTwigComponent('HistoryList', ['record' => $record]);
		$this->assertCount(2, $rendered->crawler()->filter('.timeline>li'));
		$this->assertSame('(1x)', $rendered->crawler()->filter('.timeline>li')->eq(0)->filter('small')->text());
		$this->assertSame('(5x)', $rendered->crawler()->filter('.timeline>li')->eq(1)->filter('small')->text());
	}
}
