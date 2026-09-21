<?php

namespace BugCatcher\Tests\Integration\Mcp;

use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordLog;
use BugCatcher\Mcp\Tool\RecordTools;
use BugCatcher\Tests\App\Entity\RecordCron;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\Factory\RecordCronFactory;
use BugCatcher\Tests\App\Factory\RecordLogFactory;
use BugCatcher\Tests\App\Factory\RecordLogTraceFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Kregel\ExceptionProbe\Codeframe;
use Mcp\Exception\ToolCallException;
use Zenstruck\Foundry\Test\Factories;

class RecordToolsTest extends KernelTestCase {
	use Factories;

	private RecordTools $tools;
	private Project $project;

	protected function setUp(): void {
		self::bootKernel();
		$this->tools   = self::getContainer()->get(RecordTools::class);
		$this->project = ProjectFactory::createOne(["code" => "shop", "enabled" => true])->_real();
	}

	public function testTheSearchReportsEverythingNeededToTriageABug() {
		$this->record(['hash' => 'aaa', 'date' => '2026-01-01 10:00:00', 'code' => 'E42']);
		$this->record([
			'hash'       => 'aaa',
			'date'       => '2026-01-03 10:00:00',
			'code'       => 'E42',
			'message'    => 'Division by zero',
			'requestUri' => '/checkout',
		]);

		$found = $this->tools->searchRecords(projectCode: 'shop');

		$this->assertCount(1, $found);
		// assertEquals, not assertSame: the order the keys come out in is nobody's contract
		$this->assertEquals([
			'count'           => 2,
			'date'            => '2026-01-03 10:00:00',
			'firstOccurrence' => '2026-01-01 10:00:00',
			'code'            => 'E42',
			'level'           => 500,
			'message'         => 'Division by zero',
			'requestUri'      => '/checkout',
			'status'          => 'new',
			'type'            => 'log',
			'hasStackTrace'   => false,
			'projectCode'     => 'shop',
		], array_diff_key($found[0], ['id' => null]));
	}

	public function testTheSearchHandsBackAnIdTheDetailToolAccepts() {
		$this->record(['hash' => 'aaa', 'date' => '2026-01-01 10:00:00']);

		$found = $this->tools->searchRecords(projectCode: 'shop');

		$this->assertSame(
			$found[0]['id'],
			$this->tools->getRecordDetail($found[0]['id'])['id']
		);
	}

	public function testARecordWithATraceSaysSo() {
		$this->trace(['hash' => 'aaa', 'date' => '2026-01-01 10:00:00']);

		$found = $this->tools->searchRecords(projectCode: 'shop');

		$this->assertTrue($found[0]['hasStackTrace']);
		$this->assertSame('trace-log', $found[0]['type']);
	}

	/**
	 * The summary of a type that is not a RecordLog: the fields every record has are answered from the
	 * record, and the ones only a log carries come back null rather than missing.
	 */
	public function testACustomRecordTypeIsSummarisedLikeAnyOther() {
		$this->cron([
			'lastStart' => new DateTimeImmutable('2026-01-01 09:00:00'),
			'lastEnd'   => new DateTimeImmutable('2026-01-01 09:01:00'),
		]);

		$found = $this->tools->searchRecords(projectCode: 'shop');

		$this->assertCount(1, $found);
		$this->assertSame('cron', $found[0]['type']);
		$this->assertNull($found[0]['level'], 'a cron run has no monolog level');
		$this->assertSame('app:import', $found[0]['requestUri']);
		$this->assertNotNull($found[0]['message']);
		$this->assertFalse($found[0]['hasStackTrace']);
	}

	/**
	 * What a cron run knows that no other record type does - which command, how long, how long it was
	 * meant to take - is the part worth acting on, so it has to survive the trip.
	 */
	public function testTheDetailOfACustomTypeCarriesItsOwnFields() {
		// a run that finished recently, so that it is late by the estimate and not by the interval -
		// the two states are checked in that order, and a run from 2026 is overdue whatever it did
		$start = new DateTimeImmutable('-10 minutes');
		$end   = $start->modify('+5 minutes');
		$this->cron(['lastStart' => $start, 'lastEnd' => $end, 'estimated' => 60, '_interval' => 60]);
		$id = $this->tools->searchRecords(projectCode: 'shop')[0]['id'];

		$details = $this->tools->getRecordDetail($id)['details'];

		$this->assertSame('app:import', $details['command']);
		$this->assertSame($start->format('Y-m-d H:i:s'), $details['lastStart']);
		$this->assertSame($end->format('Y-m-d H:i:s'), $details['lastEnd']);
		$this->assertSame(300, $details['runtimeSeconds']);
		$this->assertSame(60, $details['estimatedSeconds']);
		$this->assertSame(RecordCron::STATE_TOO_SLOW, $details['state']);
	}

	public function testTheDetailOfATypeWithNothingToAddSaysSoRatherThanOmittingTheKey() {
		$this->record(['hash' => 'aaa', 'date' => '2026-01-01 10:00:00']);
		$id = $this->tools->searchRecords(projectCode: 'shop')[0]['id'];

		$detail = $this->tools->getRecordDetail($id);

		$this->assertArrayHasKey('details', $detail);
		$this->assertNull($detail['details']);
	}

	/**
	 * A run that never reported an end is the run worth looking at, and the one whose end is null
	 * everywhere it is read. It must come out of both tools rather than breaking them.
	 */
	public function testARunThatNeverReportedAnEndSurvivesBothTools() {
		$this->cron([
			'command'   => 'app:stuck',
			'lastStart' => new DateTimeImmutable('-2 hours'),
			'lastEnd'   => null,
			'date'      => new DateTimeImmutable('-2 hours'),
		]);

		$found  = $this->tools->searchRecords(projectCode: 'shop');
		$detail = $this->tools->getRecordDetail($found[0]['id']);

		$this->assertNull($detail['details']['lastEnd']);
		$this->assertNull($detail['details']['runtimeSeconds']);
		$this->assertSame(RecordCron::STATE_UNFINISHED, $detail['details']['state']);
	}

	public function testOneRecordTypeCanBeSearchedOnItsOwn() {
		$this->cron();
		$this->record(['hash' => 'aaa', 'date' => '2026-01-01 10:00:00']);

		$this->assertSame(
			['cron'],
			array_column($this->tools->searchRecords(projectCode: 'shop', type: 'cron'), 'type'),
		);
		$this->assertSame(
			['log'],
			array_column($this->tools->searchRecords(projectCode: 'shop', type: 'log'), 'type'),
		);
	}

	/**
	 * The searchable types are whatever the instance configured, so they cannot be an enum in the
	 * argument schema. This message is the only place a caller can learn them.
	 */
	public function testAnUnknownTypeIsReportedBackWithTheOnesThatExist() {
		try {
			$this->tools->searchRecords(type: 'exception');
			$this->fail('an unknown type should be reported back');
		} catch (ToolCallException $e) {
			$this->assertStringContainsString('exception', $e->getMessage());
			$this->assertStringContainsString('cron', $e->getMessage());
			$this->assertStringContainsString('trace-log', $e->getMessage());
		}
	}

	/**
	 * Resolving a custom type goes through the repository the application registered for it, which is
	 * a different class from the one the bundle's own types use.
	 */
	public function testACustomRecordTypeCanBeResolved() {
		$this->cron();
		$id = $this->tools->searchRecords(projectCode: 'shop')[0]['id'];

		$this->tools->setRecordStatus($id, 'resolved');

		$this->assertSame([], $this->tools->searchRecords(projectCode: 'shop'));
		$this->assertSame(
			['cron'],
			array_column($this->tools->searchRecords(projectCode: 'shop', status: 'resolved'), 'type'),
		);
	}

	public function testAnUnknownProjectCodeIsReportedBackToTheCaller() {
		$this->expectException(ToolCallException::class);
		$this->expectExceptionMessage('nosuch');

		$this->tools->searchRecords(projectCode: 'nosuch');
	}

	public function testADateThatIsNotADateIsReportedBackToTheCaller() {
		$this->expectException(ToolCallException::class);

		$this->tools->searchRecords(dateFrom: 'last tuesday-ish');
	}

	public function testALimitBeyondWhatTheServerServesIsReportedBackToTheCaller() {
		$this->expectException(ToolCallException::class);

		$this->tools->searchRecords(limit: 5000);
	}

	public function testTheDetailCarriesTheStackTraceAsReadableText() {
		$this->trace([
			'hash'       => 'aaa',
			'date'       => '2026-01-01 10:00:00',
			'stackTrace' => serialize([
				new Codeframe('/app/src/Cpe/GponRadius.php', 58, [], 'RuntimeException: boom'),
				new Codeframe('/app/src/Runner.php', 12, [], 'run()'),
			]),
		]);
		$id = $this->tools->searchRecords(projectCode: 'shop')[0]['id'];

		$this->assertSame(
			"#0 /Cpe/GponRadius.php:58  RuntimeException: boom\n#1 /Runner.php:12  run()",
			$this->tools->getRecordDetail($id)['stackTrace']
		);
	}

	public function testTheDetailOfARecordWithoutATraceSaysSoRatherThanOmittingTheKey() {
		$this->record(['hash' => 'aaa', 'date' => '2026-01-01 10:00:00']);
		$id = $this->tools->searchRecords(projectCode: 'shop')[0]['id'];

		$detail = $this->tools->getRecordDetail($id);

		$this->assertArrayHasKey('stackTrace', $detail);
		$this->assertNull($detail['stackTrace']);
	}

	/**
	 * Whether a bug is still happening or died out on its own is the question the history answers.
	 */
	public function testTheDetailListsTheOccurrences() {
		$this->record(['hash' => 'aaa', 'date' => '2026-01-01 10:00:00']);
		$this->record(['hash' => 'aaa', 'date' => '2026-01-03 10:00:00']);
		$id = $this->tools->searchRecords(projectCode: 'shop')[0]['id'];

		$this->assertSame([
			['date' => '2026-01-03 10:00:00', 'count' => 1],
			['date' => '2026-01-01 10:00:00', 'count' => 1],
		], $this->tools->getRecordDetail($id)['history']);
	}

	public function testAnUnknownRecordIsReportedBackToTheCaller() {
		$this->expectException(ToolCallException::class);

		$this->tools->getRecordDetail('01920000-0000-7000-8000-000000000000');
	}

	public function testAnIdThatIsNotAUuidIsReportedBackToTheCaller() {
		$this->expectException(ToolCallException::class);

		$this->tools->getRecordDetail('have a guess');
	}

	/**
	 * Resolving one occurrence resolves the bug: the whole hash group goes with it, exactly as the
	 * "fix it" button of the detail page does.
	 */
	public function testResolvingARecordResolvesEveryOccurrenceOfTheSameBug() {
		$this->record(['hash' => 'aaa', 'date' => '2026-01-01 10:00:00']);
		$this->record(['hash' => 'aaa', 'date' => '2026-01-03 10:00:00']);
		$id = $this->tools->searchRecords(projectCode: 'shop')[0]['id'];

		$this->tools->setRecordStatus($id, 'resolved');

		self::getContainer()->get(EntityManagerInterface::class)->clear();
		$this->assertSame([], $this->tools->searchRecords(projectCode: 'shop'));
		$this->assertCount(1, $this->tools->searchRecords(projectCode: 'shop', status: 'resolved'));
	}

	public function testARecordCanBeArchived() {
		$this->record(['hash' => 'aaa', 'date' => '2026-01-01 10:00:00']);
		$id = $this->tools->searchRecords(projectCode: 'shop')[0]['id'];

		$this->tools->setRecordStatus($id, 'archived');

		self::getContainer()->get(EntityManagerInterface::class)->clear();
		$this->assertCount(1, $this->tools->searchRecords(projectCode: 'shop', status: 'archived'));
	}

	/**
	 * RecordRepository::getUpdateStatusQB() interpolates the status straight into DQL, so anything
	 * but the two known values has to be refused before it gets there.
	 *
	 * @dataProvider refusedStatuses
	 */
	public function testAStatusOutsideTheWhitelistIsRefused(string $status) {
		$this->record(['hash' => 'aaa', 'date' => '2026-01-01 10:00:00']);
		$id = $this->tools->searchRecords(projectCode: 'shop')[0]['id'];

		$this->expectException(ToolCallException::class);

		$this->tools->setRecordStatus($id, $status);
	}

	public function refusedStatuses(): array {
		return [
			'made up'       => ['deleted'],
			'back to new'   => ['new'],
			'dql injection' => ["resolved' WHERE 1=1 OR l.status='new"],
			'empty'         => [''],
		];
	}

	public function testSettingTheStatusOfAnUnknownRecordIsReportedBackToTheCaller() {
		$this->expectException(ToolCallException::class);

		$this->tools->setRecordStatus('01920000-0000-7000-8000-000000000000', 'resolved');
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	/**
	 * A record type of the application's own, which is the case the tools have to keep working for.
	 *
	 * @param array<string, mixed> $attributes
	 */
	private function cron(array $attributes = []): void {
		RecordCronFactory::createOne([
			'project' => $this->project,
			'status'  => 'new',
			'command' => 'app:import',
			'date'    => new DateTimeImmutable('2026-01-01 10:00:00'),
			...$attributes,
		]);
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	private function record(array $attributes): void {
		RecordLogFactory::createOne($this->attributes($attributes));
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	private function trace(array $attributes): void {
		RecordLogTraceFactory::createOne($this->attributes($attributes) + ['stackTrace' => 'whatever']);
	}

	/**
	 * @param array<string, mixed> $attributes
	 *
	 * @return array<string, mixed>
	 */
	private function attributes(array $attributes): array {
		return [
			'status'     => 'new',
			'level'      => 500,
			'code'       => null,
			'message'    => 'boom',
			'requestUri' => '/',
			'project'    => $this->project,
			...$attributes,
			'date'       => new DateTimeImmutable($attributes['date']),
		];
	}
}
