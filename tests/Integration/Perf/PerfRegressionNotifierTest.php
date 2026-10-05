<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Perf;

use BugCatcher\DTO\NotifierStatus;
use BugCatcher\Entity\Notifier;
use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Enum\Importance;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Event\NotifyCalculateEvent;
use BugCatcher\Tests\App\Factory\NotifierEmailFactory;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordLogFactory;
use BugCatcher\Tests\App\KernelTestCase;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Zenstruck\Foundry\Test\Factories;

/**
 * `perf-regression-count`: the notifier component that counts only slow routes.
 *
 * Regressions have always reached the notifiers - `RecordPerformanceWriter` dispatches a
 * `RecordEvent` itself, and the other two components count every unresolved `Record` with no
 * discriminator filter. What they could not do is be told about regressions *alone*, at a threshold
 * chosen for regressions: twenty exceptions and twenty routes that got slower are not the same news.
 */
final class PerfRegressionNotifierTest extends KernelTestCase
{
	use Factories;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne(['enabled' => true])->_real();
	}

	/** The whole point: a log error does not raise a notifier that was pointed at performance. */
	public function testItCountsRegressionsAndIgnoresLogErrors(): void
	{
		RecordLogFactory::createMany(5, ['project' => $this->project]);
		$this->regression('/checkout');
		$this->regression('/cart');

		$status = $this->calculate('perf-regression-count');

		$this->assertNotNull($status);
		$this->assertSame($this->project->getId()->toRfc4122(), $status->project->getId()->toRfc4122());

		// `Importance::High` is the *group* the count is added to, not the level that comes out:
		// NotifierStatus scales the count against the threshold, so two regressions over a
		// threshold of one saturate well above High. What matters here is that it is high enough
		// to notify at all.
		$this->assertTrue($status->getImportance()->isHigherOrEqualThan(Importance::High));
	}

	/**
	 * And the converse, which is the behaviour this change deliberately did *not* touch: the two
	 * existing components still count everything, regressions included. Changing that would
	 * silently lower every installation's error count on upgrade.
	 */
	public function testTheOlderComponentsStillCountRegressionsToo(): void
	{
		$this->regression('/checkout');

		$this->assertNotNull($this->calculate('project-error-count'));
		$this->assertNotNull($this->calculate('same-error-count'));
	}

	/** No regressions is no status at all, not a status of zero - a notifier has nothing to clear. */
	public function testAProjectWithOnlyLogErrorsProducesNoStatus(): void
	{
		RecordLogFactory::createMany(5, ['project' => $this->project]);

		$this->assertNull($this->calculate('perf-regression-count'));
	}

	/** A regression somebody has already dealt with is not news. */
	public function testAResolvedRegressionIsNotCounted(): void
	{
		$record = $this->regression('/checkout');
		$record->setStatus('resolved');
		$this->em()->flush();

		$this->assertNull($this->calculate('perf-regression-count'));
	}

	/**
	 * The count is per project and the threshold is the notifier's, so a project under its
	 * threshold stays below `minimalImportance` and a project over it does not.
	 */
	public function testTheThresholdIsTheNotifiersOwn(): void
	{
		$this->regression('/checkout');
		$this->regression('/cart');
		$this->regression('/search');

		$under = $this->calculate('perf-regression-count', threshold: 10);
		$over  = $this->calculate('perf-regression-count', threshold: 1);

		$this->assertNotNull($under);
		$this->assertNotNull($over);
		$this->assertTrue(
			$over->getImportance()->isHigherOrEqualThan($under->getImportance()),
			'a lower threshold cannot produce a lower importance',
		);
	}

	/**
	 * A record written between two calculations is visible to the second one.
	 *
	 * This is what the 10-second `enableResultCache()` on the two older queries broke, and the
	 * reason it was removed. `RecordPerformanceWriter` dispatches one `RecordEvent` per written
	 * record in a tight loop, so inside a single `app:perf:detect` run every event after the first
	 * read counts from before the flush - and a notifier could fail to cross its threshold on the
	 * very run that gave it something to cross it with.
	 *
	 * Thresholded at ten so the two counts land on different levels: `NotifierStatus` scales the
	 * count against the threshold, and at a threshold of one everything saturates and a stale read
	 * would be indistinguishable from a fresh one.
	 *
	 * @dataProvider everyComponent
	 */
	public function testACountIsNeverServedFromBeforeTheLastWrite(string $component): void
	{
		$this->regression('/a');
		$this->regression('/b');

		$firstRead = $this->calculate($component, threshold: 10);

		foreach (['/c', '/d', '/e', '/f'] as $path) {
			$this->regression($path);
		}

		$secondRead = $this->calculate($component, threshold: 10);

		$this->assertNotNull($firstRead);
		$this->assertNotNull($secondRead);
		$this->assertTrue(
			$secondRead->getImportance()->isHigherThan($firstRead->getImportance()),
			sprintf(
				'%s read a count from before the last write: %s then %s',
				$component,
				$firstRead->getImportance()->value,
				$secondRead->getImportance()->value,
			),
		);
	}

	public static function everyComponent(): iterable
	{
		yield 'project-error-count' => ['project-error-count'];
		yield 'same-error-count' => ['same-error-count'];
		yield 'perf-regression-count' => ['perf-regression-count'];
	}

	/** The status carried back for this project, or null if the component produced none. */
	private function calculate(string $component, int $threshold = 1): ?NotifierStatus
	{
		$notifier = NotifierEmailFactory::createOne([
			'projects'          => new ArrayCollection([$this->project]),
			'minimalImportance' => Importance::Low,
			'threshold'         => $threshold,
			'component'         => $component,
		])->_real();

		$event = new NotifyCalculateEvent($notifier);
		$this->dispatcher()->dispatch($event);

		foreach ($event->getStatuses() as $status) {
			if ($status->project->getId()->toRfc4122() === $this->project->getId()->toRfc4122()) {
				return $status;
			}
		}

		return null;
	}

	private function regression(string $path): RecordPerformance
	{
		$record = new RecordPerformance(
			$this->project,
			$path,
			'p95',
			PerfUnit::Milliseconds,
			210.0,
			3100.0,
			new \DateTimeImmutable('-5 minutes'),
		);
		$record->setHash($record->calculateHash());

		$this->em()->persist($record);
		$this->em()->flush();

		return $record;
	}

	private function em(): EntityManagerInterface
	{
		return self::getContainer()->get(EntityManagerInterface::class);
	}

	private function dispatcher(): EventDispatcherInterface
	{
		return self::getContainer()->get(EventDispatcherInterface::class);
	}
}
