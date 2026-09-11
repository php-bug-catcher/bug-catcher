<?php

namespace BugCatcher\Tests\Integration\Mcp;

use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordLog;
use BugCatcher\Mcp\Tool\RecordTools;
use BugCatcher\Tests\App\Factory\ProjectFactory;
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
