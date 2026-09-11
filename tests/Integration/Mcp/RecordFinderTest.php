<?php

namespace BugCatcher\Tests\Integration\Mcp;

use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordLog;
use BugCatcher\Mcp\RecordFinder;
use BugCatcher\Mcp\RecordSearchCriteria;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordLogFactory;
use BugCatcher\Tests\App\Factory\RecordLogTraceFactory;
use BugCatcher\Tests\App\Factory\RecordPingFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\Factories;

class RecordFinderTest extends KernelTestCase {
	use Factories;

	private RecordFinder $finder;
	private Project $project;

	protected function setUp(): void {
		self::bootKernel();
		// built by hand rather than pulled from the container: the finder takes nothing but the
		// entity manager, and fetching it as a service would only work while some other service
		// happens to reference it
		$this->finder  = new RecordFinder(self::getContainer()->get(EntityManagerInterface::class));
		$this->project = ProjectFactory::createOne(["code" => "shop", "enabled" => true])->_real();
	}

	/**
	 * Records repeat: the same bug reported a thousand times is a thousand rows sharing one hash.
	 * The reader wants one entry per bug, carrying how bad it is.
	 */
	public function testRecordsOfOneBugCollapseIntoASingleEntry() {
		$this->record(['hash' => 'aaa', 'date' => '2026-01-01 10:00:00', 'message' => 'oldest']);
		$this->record(['hash' => 'aaa', 'date' => '2026-01-02 10:00:00', 'message' => 'middle']);
		$this->record(['hash' => 'aaa', 'date' => '2026-01-03 10:00:00', 'message' => 'newest']);

		$found = $this->finder->search($this->criteria());

		$this->assertCount(1, $found);
		$this->assertSame('aaa', $found[0]->getHash());
	}

	/**
	 * The count has to be the whole truth, not "how many fit in the page". An AI prioritising bugs
	 * reads it as "how often does this happen", and a windowed number would rank them wrongly.
	 */
	public function testTheCountIsTheTotalOfTheGroupNotOfTheFetchedPage() {
		for ($day = 1; $day <= 7; $day++) {
			$this->record(['hash' => 'aaa', 'date' => sprintf('2026-01-%02d 10:00:00', $day)]);
		}

		$found = $this->finder->search($this->criteria(limit: 1));

		$this->assertSame(7, $found[0]->getCount());
	}

	public function testTheEntryCarriesTheNewestOccurrenceAndTheFirstOneSeparately() {
		$this->record(['hash' => 'aaa', 'date' => '2026-01-01 10:00:00', 'message' => 'oldest']);
		$this->record(['hash' => 'aaa', 'date' => '2026-01-03 10:00:00', 'message' => 'newest']);

		$found = $this->finder->search($this->criteria());

		$this->assertSame('newest', $found[0]->getMessage(), 'the entry should represent the latest occurrence');
		$this->assertSame('2026-01-03 10:00:00', $found[0]->getDate()->format('Y-m-d H:i:s'));
		$this->assertSame('2026-01-01 10:00:00', $found[0]->getFirstOccurrence()->format('Y-m-d H:i:s'));
	}

	public function testTheMostRecentlySeenBugComesFirst() {
		$this->record(['hash' => 'old', 'date' => '2026-01-01 10:00:00']);
		$this->record(['hash' => 'new', 'date' => '2026-01-05 10:00:00']);
		$this->record(['hash' => 'mid', 'date' => '2026-01-03 10:00:00']);

		$this->assertSame(
			['new', 'mid', 'old'],
			array_map(fn(RecordLog $r) => $r->getHash(), $this->finder->search($this->criteria()))
		);
	}

	/**
	 * The limit counts distinct bugs, not rows - that is what the caller asked for.
	 */
	public function testTheLimitCapsTheNumberOfDistinctBugs() {
		foreach (['a', 'b', 'c'] as $hash) {
			$this->record(['hash' => $hash, 'date' => '2026-01-01 10:00:00']);
			$this->record(['hash' => $hash, 'date' => '2026-01-02 10:00:00']);
		}

		$this->assertCount(2, $this->finder->search($this->criteria(limit: 2)));
	}

	public function testOnlyTheRequestedProjectIsSearched() {
		$other = ProjectFactory::createOne(["code" => "blog", "enabled" => true])->_real();
		$this->record(['hash' => 'mine', 'date' => '2026-01-01 10:00:00']);
		$this->record(['hash' => 'theirs', 'date' => '2026-01-01 10:00:00', 'project' => $other]);

		$found = $this->finder->search($this->criteria());

		$this->assertSame(['mine'], array_map(fn(RecordLog $r) => $r->getHash(), $found));
	}

	public function testWithoutAProjectEveryProjectIsSearched() {
		$other = ProjectFactory::createOne(["code" => "blog", "enabled" => true])->_real();
		$this->record(['hash' => 'mine', 'date' => '2026-01-01 10:00:00']);
		$this->record(['hash' => 'theirs', 'date' => '2026-01-02 10:00:00', 'project' => $other]);

		$found = $this->finder->search(new RecordSearchCriteria());

		$this->assertSame(['theirs', 'mine'], array_map(fn(RecordLog $r) => $r->getHash(), $found));
	}

	/**
	 * The dashboard matches the status as a prefix, so "new" also finds the withheld variants the
	 * notification throttle parks under a longer name. The tool keeps that behaviour.
	 */
	public function testTheStatusIsMatchedAsAPrefix() {
		$this->record(['hash' => 'plain', 'date' => '2026-01-01 10:00:00', 'status' => 'new']);
		$this->record(['hash' => 'longer', 'date' => '2026-01-01 10:00:00', 'status' => 'new-withheld']);
		$this->record(['hash' => 'done', 'date' => '2026-01-01 10:00:00', 'status' => 'resolved']);

		$found = $this->finder->search($this->criteria());

		$this->assertEqualsCanonicalizing(
			['plain', 'longer'],
			array_map(fn(RecordLog $r) => $r->getHash(), $found)
		);
	}

	public function testResolvedRecordsCanBeAskedForExplicitly() {
		$this->record(['hash' => 'open', 'date' => '2026-01-01 10:00:00', 'status' => 'new']);
		$this->record(['hash' => 'done', 'date' => '2026-01-01 10:00:00', 'status' => 'resolved']);

		$found = $this->finder->search($this->criteria(status: 'resolved'));

		$this->assertSame(['done'], array_map(fn(RecordLog $r) => $r->getHash(), $found));
	}

	public function testTheErrorCodeNarrowsTheSearch() {
		$this->record(['hash' => 'wanted', 'date' => '2026-01-01 10:00:00', 'code' => 'E42']);
		$this->record(['hash' => 'other', 'date' => '2026-01-01 10:00:00', 'code' => 'E13']);

		$found = $this->finder->search($this->criteria(code: 'E42'));

		$this->assertSame(['wanted'], array_map(fn(RecordLog $r) => $r->getHash(), $found));
	}

	public function testTheLevelFloorLeavesTheQuieterRecordsOut() {
		$this->record(['hash' => 'critical', 'date' => '2026-01-01 10:00:00', 'level' => 500]);
		$this->record(['hash' => 'notice', 'date' => '2026-01-01 10:00:00', 'level' => 250]);

		$found = $this->finder->search($this->criteria(minLevel: 400));

		$this->assertSame(['critical'], array_map(fn(RecordLog $r) => $r->getHash(), $found));
	}

	public function testTheDateRangeIsInclusiveOnBothEnds() {
		$this->record(['hash' => 'before', 'date' => '2026-01-01 23:59:59']);
		$this->record(['hash' => 'from', 'date' => '2026-01-02 00:00:00']);
		$this->record(['hash' => 'to', 'date' => '2026-01-03 00:00:00']);
		$this->record(['hash' => 'after', 'date' => '2026-01-03 00:00:01']);

		$found = $this->finder->search($this->criteria(
			from: new DateTimeImmutable('2026-01-02 00:00:00'),
			to: new DateTimeImmutable('2026-01-03 00:00:00'),
		));

		$this->assertEqualsCanonicalizing(
			['from', 'to'],
			array_map(fn(RecordLog $r) => $r->getHash(), $found)
		);
	}

	/**
	 * A ping result is a Record too, but it is not an error anyone fixes in a code base, and it
	 * carries neither message nor level.
	 */
	public function testPingResultsAreNotErrors() {
		RecordPingFactory::createOne([
			'hash'    => 'ping',
			'date'    => new DateTimeImmutable('2026-01-01 10:00:00'),
			'status'  => 'new',
			'project' => $this->project,
		]);
		$this->record(['hash' => 'real', 'date' => '2026-01-01 10:00:00']);

		$found = $this->finder->search($this->criteria());

		$this->assertSame(['real'], array_map(fn(RecordLog $r) => $r->getHash(), $found));
	}

	public function testRecordsCarryingAStackTraceAreFoundToo() {
		RecordLogTraceFactory::createOne([
			'hash'       => 'traced',
			'date'       => new DateTimeImmutable('2026-01-01 10:00:00'),
			'status'     => 'new',
			'level'      => 500,
			'project'    => $this->project,
			'stackTrace' => 'whatever',
		]);

		$found = $this->finder->search($this->criteria());

		$this->assertCount(1, $found);
		$this->assertInstanceOf(\BugCatcher\Entity\RecordLogTrace::class, $found[0]);
	}

	public function testNothingMatchingGivesAnEmptyResult() {
		$this->assertSame([], $this->finder->search($this->criteria()));
	}

	/**
	 * The history is what tells the reader whether a bug is still happening or died out on its own.
	 */
	public function testTheHistoryListsTheOccurrencesNewestFirst() {
		$this->record(['hash' => 'aaa', 'date' => '2026-01-01 10:00:00']);
		$this->record(['hash' => 'aaa', 'date' => '2026-01-03 10:00:00']);
		$this->record(['hash' => 'aaa', 'date' => '2026-01-02 10:00:00']);

		$found   = $this->finder->search($this->criteria());
		$history = $this->finder->history($found[0]);

		$this->assertSame(
			['2026-01-03 10:00:00', '2026-01-02 10:00:00', '2026-01-01 10:00:00'],
			array_map(fn(RecordLog $r) => $r->getDate()->format('Y-m-d H:i:s'), $history)
		);
	}

	public function testTheHistoryStaysWithinTheStatusTheRecordIsIn() {
		$this->record(['hash' => 'aaa', 'date' => '2026-01-01 10:00:00', 'status' => 'new']);
		$this->record(['hash' => 'aaa', 'date' => '2026-01-02 10:00:00', 'status' => 'resolved']);

		$found = $this->finder->search($this->criteria());

		$this->assertCount(1, $this->finder->history($found[0]));
	}

	private function criteria(
		?string            $status = 'new',
		?string            $code = null,
		?int               $minLevel = null,
		?DateTimeImmutable $from = null,
		?DateTimeImmutable $to = null,
		int                $limit = 25,
	): RecordSearchCriteria {
		return new RecordSearchCriteria($this->project, $status, $code, $minLevel, $from, $to, $limit);
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	private function record(array $attributes): void {
		RecordLogFactory::createOne([
			'status'  => 'new',
			'level'   => 500,
			'code'    => null,
			'project' => $this->project,
			...$attributes,
			'date'    => new DateTimeImmutable($attributes['date']),
		]);
	}
}
