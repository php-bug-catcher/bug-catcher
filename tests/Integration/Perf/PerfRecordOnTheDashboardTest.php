<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Perf;

use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\UserFactory;
use BugCatcher\Tests\App\KernelTestCase;
use BugCatcher\Tests\Integration\Trait\SessionInterfaceTrait;
use BugCatcher\Twig\Components\LogList;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;
use Zenstruck\Foundry\Test\Factories;

/**
 * A regression is of no use to anybody if it is not on the page. What puts it there is the
 * `dashboard_list_items` default in config/definition.php, and this is what says so.
 */
class PerfRecordOnTheDashboardTest extends KernelTestCase
{
	use Factories;
	use InteractsWithTwigComponents;
	use SessionInterfaceTrait;

	public function testARegressionIsOneOfTheRowsTheDashboardLists(): void
	{
		$project = $this->loggedInWithAProject();
		$this->regression($project, '/checkout');

		$logs = $this->logList();

		$this->assertCount(1, $logs);
		$this->assertInstanceOf(RecordPerformance::class, $logs[0]);
		$this->assertSame('p95 latency on /checkout rose from 210 ms to 3.1 s', $logs[0]->getMessage());
	}

	/**
	 * The dashboard groups by hash, so a regression that lasts an hour is one row with a count
	 * rather than twelve rows - which is the whole reason the window is not part of the hash.
	 */
	public function testTheSameRouteRegressingAgainIsOneRowWithACount(): void
	{
		$project = $this->loggedInWithAProject();
		$this->regression($project, '/checkout', '2026-03-10 14:35:00');
		$this->regression($project, '/checkout', '2026-03-10 14:40:00');
		$this->regression($project, '/cart');

		$logs = $this->logList();

		$this->assertCount(2, $logs);
		$counts = [];
		foreach ($logs as $log) {
			$counts[$log->getPath()] = $log->getCount();
		}
		$this->assertSame(['/checkout' => 2, '/cart' => 1], $counts);
	}

	/** @return list<\BugCatcher\Entity\Record> */
	private function logList(): array
	{
		$component = $this->mountTwigComponent('LogList', ['status' => 'new']);
		$this->assertInstanceOf(LogList::class, $component);
		$component->init();

		return array_values($component->logs);
	}

	private function loggedInWithAProject(): Project
	{
		$this->initSession();
		$user    = UserFactory::createOne();
		$project = ProjectFactory::createOne([
			'users'   => new ArrayCollection([$user->_real()]),
			'enabled' => true,
		])->_real();
		$user->_refresh();
		$this->loginUser($user->_real());

		return $project;
	}

	private function regression(Project $project, string $path, string $windowAt = '2026-03-10 14:35:00'): void
	{
		$record = new RecordPerformance(
			$project,
			$path,
			'p95',
			PerfUnit::Milliseconds,
			210.0,
			3100.0,
			new DateTimeImmutable($windowAt),
		);
		$record->setHash($record->calculateHash());

		$em = self::getContainer()->get(EntityManagerInterface::class);
		$em->persist($record);
		$em->flush();
	}
}
