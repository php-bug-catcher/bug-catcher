<?php

namespace BugCatcher\Tests\Integration\Mcp;

use BugCatcher\Entity\Project;
use BugCatcher\Entity\Record;
use BugCatcher\Entity\RecordLog;
use BugCatcher\Entity\RecordLogTrace;
use BugCatcher\Mcp\RecordFinder;
use BugCatcher\Mcp\RecordSearchCriteria;
use BugCatcher\Mcp\RecordTypes;
use BugCatcher\Tests\App\Entity\RecordCron;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordCronFactory;
use BugCatcher\Tests\App\Factory\RecordLogFactory;
use BugCatcher\Tests\App\Factory\RecordLogTraceFactory;
use BugCatcher\Tests\App\Factory\RecordPingFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

class RecordFinderTest extends KernelTestCase {
	use Factories;

	private RecordFinder $finder;
	private Project $project;

	protected function setUp(): void {
		self::bootKernel();
		// built by hand rather than pulled from the container: fetching it as a service would only
		// work while some other service happens to reference it, and the allowlist is what several
		// of these tests vary
		$this->finder  = $this->finderFor(RecordLog::class, RecordCron::class);
		$this->project = ProjectFactory::createOne(["code" => "shop", "enabled" => true])->_real();
	}

	/**
	 * A finder configured with a given allowlist, the way an application's `mcp.record_types` does it.
	 *
	 * @param class-string<Record> ...$classes
	 */
	private function finderFor(string ...$classes): RecordFinder {
		$em = self::getContainer()->get(EntityManagerInterface::class);

		return new RecordFinder($em, new RecordTypes($em, $classes));
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
	 * The point of the whole allowlist: a record type an application added is collected and shown on
	 * the dashboard, so it has to be readable here as well, and not only the types this bundle ships.
	 */
	public function testAConfiguredCustomRecordTypeIsFound() {
		$this->cron('app:import');

		$found = $this->finder->search($this->criteria());

		$this->assertCount(1, $found);
		$this->assertInstanceOf(RecordCron::class, $found[0]);
	}

	/**
	 * The representative is hydrated fully, not partially. A partial entity would answer null to every
	 * field of the subtype without saying that it had not read them, and the details of the record
	 * would quietly come out empty.
	 */
	public function testTheEntryOfACustomTypeCarriesItsOwnFields() {
		$this->cron('app:import');

		/** @var RecordCron $found */
		$found = $this->finder->search($this->criteria())[0];

		$this->assertSame('app:import', $found->getCommand());
		$this->assertNotNull($found->getLastStart());
		$this->assertSame(60, $found->getInterval());
	}

	public function testACustomTypeIsInvisibleToAFinderNotConfiguredForIt() {
		$this->cron('app:import');
		$this->record(['hash' => 'real', 'date' => '2026-01-01 10:00:00']);

		$found = $this->finderFor(RecordLog::class)->search($this->criteria());

		$this->assertSame(['real'], array_map(fn(Record $r) => $r->getHash(), $found));
	}

	/**
	 * Grouping has to work for a type that is not a RecordLog too - the counting happens on the
	 * columns of the shared root table, which is exactly why it can.
	 */
	public function testTheRunsOfOneCommandCollapseIntoASingleEntry() {
		$this->cron('app:import', '-3 hours');
		$this->cron('app:import', '-2 hours');
		$this->cron('app:other', '-1 hour');

		$found = $this->finder->search($this->criteria());

		$this->assertCount(2, $found);
		$this->assertSame(1, $found[0]->getCount(), 'app:other ran once and is the most recent');
		$this->assertSame(2, $found[1]->getCount());
	}

	/**
	 * Asking for a level floor asks for the records that carry a level at all. A cron run has none, so
	 * it drops out rather than passing itself off as level 0.
	 */
	public function testTheLevelFloorLeavesOutATypeThatHasNoLevel() {
		$this->cron('app:import');
		$this->record(['hash' => 'critical', 'date' => '2026-01-01 10:00:00', 'level' => 500]);

		$found = $this->finder->search($this->criteria(minLevel: 400));

		$this->assertSame(['critical'], array_map(fn(Record $r) => $r->getHash(), $found));
	}

	public function testOneTypeCanBeAskedForOnItsOwn() {
		$this->cron('app:import');
		$this->record(['hash' => 'real', 'date' => '2026-01-01 10:00:00']);
		RecordLogTraceFactory::createOne([
			'hash'       => 'traced',
			'date'       => new DateTimeImmutable('2026-01-01 10:00:00'),
			'status'     => 'new',
			'level'      => 500,
			'project'    => $this->project,
			'stackTrace' => 'whatever',
		]);

		$this->assertCount(1, $this->finder->search($this->criteria(type: 'cron')));
		$this->assertSame(
			['real'],
			array_map(fn(Record $r) => $r->getHash(), $this->finder->search($this->criteria(type: 'log'))),
		);
	}

	/**
	 * An id of a type the server does not search is as good as an unknown one. Otherwise a ping id
	 * would reach get_record_detail, which asks it for a component name it throws on.
	 */
	public function testARecordOutsideTheAllowlistCannotBeFetchedById() {
		$ping = RecordPingFactory::createOne([
			'hash'    => 'ping',
			'date'    => new DateTimeImmutable('2026-01-01 10:00:00'),
			'status'  => 'new',
			'project' => $this->project,
		])->_real();

		$this->assertNull($this->finder->find($ping->getId()));
	}

	public function testAnUnknownIdIsSimplyNotFound() {
		$this->assertNull($this->finder->find(Uuid::v7()));
	}

	/**
	 * The history of a traced bug legitimately mixes the two log types - the same message reported once
	 * with a trace and once without shares a hash - so the allowlist, not the record's own class, is
	 * what bounds it.
	 */
	public function testTheHistoryOfATracedBugStillIncludesTheUntracedOccurrences() {
		$this->record(['hash' => 'aaa', 'date' => '2026-01-01 10:00:00']);
		RecordLogTraceFactory::createOne([
			'hash'       => 'aaa',
			'date'       => new DateTimeImmutable('2026-01-02 10:00:00'),
			'status'     => 'new',
			'level'      => 500,
			'project'    => $this->project,
			'stackTrace' => 'whatever',
		]);

		$found = $this->finder->search($this->criteria());

		$this->assertInstanceOf(RecordLogTrace::class, $found[0]);
		$this->assertCount(2, $this->finder->history($found[0]));
	}

	/**
	 * `lastEnd` is nullable, and a run that never reported one is precisely the run worth looking at.
	 * Reading it must not blow up on the way out.
	 */
	public function testARunThatNeverReportedAnEndIsStillReadable() {
		RecordCronFactory::createOne([
			'project'   => $this->project,
			'status'    => 'new',
			'command'   => 'app:stuck',
			'lastStart' => new DateTimeImmutable('-2 hours'),
			'lastEnd'   => null,
		]);

		$found = $this->finder->search($this->criteria());

		$this->assertCount(1, $found);
		$this->assertStringContainsString('did not finish', $found[0]->getMessage());
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
		?string            $type = null,
	): RecordSearchCriteria {
		return new RecordSearchCriteria(
			$this->project, $status, $code, $minLevel, $from, $to, $limit, $type,
		);
	}

	/**
	 * A finished run of one command, at a date of its own so the ordering is predictable.
	 */
	private function cron(string $command, string $ago = '-1 hour'): void {
		RecordCronFactory::createOne([
			'project'   => $this->project,
			'status'    => 'new',
			'command'   => $command,
			'date'      => new DateTimeImmutable($ago),
			'lastStart' => new DateTimeImmutable($ago),
			'lastEnd'   => new DateTimeImmutable($ago),
		]);
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
