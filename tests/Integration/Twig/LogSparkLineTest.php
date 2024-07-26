<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 26. 7. 2024
 * Time: 8:09
 */
namespace PhpSentinel\BugCatcher\Tests\Integration\Twig;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use PhpSentinel\BugCatcher\Factory\ProjectFactory;
use PhpSentinel\BugCatcher\Factory\RecordLogFactory;
use PhpSentinel\BugCatcher\Twig\Components\LogSparkLine;
use PhpSentinel\BugCatcher\Tests\Integration\KernelTestCase;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class LogSparkLineTest extends KernelTestCase {
	use InteractsWithTwigComponents;
	use ResetDatabase;
	use Factories;

	public function testSparkLineIntervals() {
		$project = ProjectFactory::createOne([
			"enabled" => true,
		]);
		$date    = new DateTimeImmutable();
		$date    = $date->setTime($date->format("H"), 3, 50);
		for ($i = 0; $i < 30; $i++) {
			RecordLogFactory::createMany($i + 1, [
				"date"    => $date->modify("-{$i} hours -{$i} seconds"),
				"status"  => "new",
				"project" => $project,
			]);
		}
		$rendered = $this->mountTwigComponent('LogSparkLine', ["project" => $project->_real()]);
		$this->assertInstanceOf(LogSparkLine::class, $rendered);
		$intervals = $rendered->getSparkLineIntervals();
		$date      = new DateTimeImmutable();
		$date      = $date->setTime(((int)$date->format("H")) + 1, 00, 00)->modify("-{$rendered->graphHours} hours");
		$this->assertCount($rendered->graphHours, $intervals);
		for ($i = 0; $i < $rendered->graphHours; $i++) {
			$this->assertSame($rendered->graphHours - $i, $intervals[$i]->count);
			$expected = $date->modify("+{$i} hours")->format("Y-m-d H:i:s");
			$this->assertSame($expected, $intervals[$i]->dateTime->format("Y-m-d H:i:s"));
		}
	}
}
