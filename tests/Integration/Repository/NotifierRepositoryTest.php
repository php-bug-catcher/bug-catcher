<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 9. 6. 2024
 * Time: 16:31
 */
namespace BugCatcher\Tests\Integration\Repository;

use DateTimeImmutable;
use BugCatcher\Entity\NotifierFavicon;
use BugCatcher\Enum\NotifyRepeat;
use BugCatcher\Repository\NotifierRepository;
use BugCatcher\Tests\App\KernelTestCase;

class NotifierRepositoryTest extends KernelTestCase {

	public function set() {

	}
	public function getService(): NotifierRepository {
		return self::getContainer()->get(NotifierRepository::class);
	}

	/**
	 * @dataProvider canClearProvider
	 */
	public function testCanClear(NotifyRepeat $repeat, int $interval, int $okStatusCount, string $fistStatus, bool $expected): void {
		$notifier = new NotifierFavicon();
		$notifier
			->setClearAt($repeat)
			->setClearInterval($interval)
			->setLastOkStatusCount($okStatusCount)
			->setFirstOkStatus(new DateTimeImmutable($fistStatus));
		$result = $this->getService()->canClear($notifier);
		$this->assertSame($expected, $result);
	}

	public function canClearProvider(): iterable {
		yield [NotifyRepeat::None, 0, 0, 'now', true];
		yield [NotifyRepeat::FrequencyRecords, 2, 2, 'now', true];
		yield [NotifyRepeat::FrequencyRecords, 2, 1, 'now', false];
		yield [NotifyRepeat::FrequencyRecords, 2, 0, 'now', false];
		yield [NotifyRepeat::PeriodTime, 60, 0, '-1 hours', true];
		yield [NotifyRepeat::PeriodTime, 65, 0, '-1 hours', true];
		yield [NotifyRepeat::PeriodTime, 60, 0, '-40 seconds', false];
	}

	/**
	 * @dataProvider checkRepeatProvider
	 */
	public function testCheckRepeat(NotifyRepeat $repeat, int $interval, ?string $lastNotifies, int $repeatAtSkipped, bool $expected) {
		$notifier = new NotifierFavicon();
		$notifier
			->setRepeat($repeat)
			->setRepeatInterval($interval)
			->setLastNotified($lastNotifies ? new DateTimeImmutable($lastNotifies) : null)
			->setRepeatAtSkipped($repeatAtSkipped);
		$result = $this->getService()->checkRepeat($notifier);
		$this->assertSame($expected, $result);
	}

	public function checkRepeatProvider(): iterable {
		yield [NotifyRepeat::None, 0, null, 0, true];
		yield [NotifyRepeat::None, 0, 'now', 0, false];
		yield [NotifyRepeat::FrequencyRecords, 2, 'now', 0, true];
		yield [NotifyRepeat::FrequencyRecords, 2, 'now', 1, false];
		yield [NotifyRepeat::FrequencyRecords, 2, 'now', 2, true];
		yield [NotifyRepeat::PeriodTime, 60, null, 0, true];
		yield [NotifyRepeat::PeriodTime, 60, '-40 seconds', 0, false];
		yield [NotifyRepeat::PeriodTime, 60, '-1 minutes', 0, true];
		yield [NotifyRepeat::PeriodTime, 60, '-3 minutes', 0, true];
	}

	/**
	 * @dataProvider checkDelayProvider
	 */
	public function testCheckDelay(NotifyRepeat $repeat, int $interval, int $failedStatusCount, ?string $lastFailedStatus, bool $expected) {
		$notifier = new NotifierFavicon();
		$notifier
			->setDelay($repeat)
			->setDelayInterval($interval)
			->setFailedStatusCount($failedStatusCount)
			->setLastFailedStatus($lastFailedStatus?new DateTimeImmutable($lastFailedStatus):null);
		$result = $this->getService()->isDelayed($notifier);
		$this->assertSame($expected, $result);
	}

	public function checkDelayProvider(): iterable {
		yield [NotifyRepeat::None, 0, 0, 'now', false];
		yield [NotifyRepeat::FrequencyRecords, 2, 2, 'now', false];
		yield [NotifyRepeat::FrequencyRecords, 2, 1, 'now', true];
		yield [NotifyRepeat::FrequencyRecords, 2, 0, 'now', true];
		yield [NotifyRepeat::PeriodTime, 50, 0, '-1 minute', false];
		yield [NotifyRepeat::PeriodTime, 60, 0, '-1 minute', false];
		yield [NotifyRepeat::PeriodTime, 65, 0, '-1 minute', true];
	}
}
