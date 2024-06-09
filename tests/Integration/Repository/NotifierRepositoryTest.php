<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 9. 6. 2024
 * Time: 16:31
 */
namespace PhpSentinel\BugCatcher\Tests\Integration\Repository;

use PhpSentinel\BugCatcher\Entity\NotifierFavicon;
use PhpSentinel\BugCatcher\Enum\NotifyRepeat;
use PhpSentinel\BugCatcher\Repository\NotifierRepository;
use PhpSentinel\BugCatcher\Tests\Integration\KernelTestCase;

class NotifierRepositoryTest extends KernelTestCase {

	public function getService(): NotifierRepository {
		return self::getContainer()->get(NotifierRepository::class);
	}

	/**
	 * @dataProvider canClearProvider
	 */
	public function testCanClear(NotifyRepeat $repeat, int $interval, int $okStatusCount, string $fistStatus, bool $expected):void {
		$notifier = new NotifierFavicon();
		$notifier
			->setClearAt($repeat)
			->setClearInterval($interval)
			->setLastOkStatusCount($okStatusCount)
			->setFirstOkStatus(new \DateTimeImmutable($fistStatus));
		$result = $this->getService()->canClear($notifier);
		$this->assertSame($expected, $result);
	}

	public function canClearProvider():iterable {
		yield [NotifyRepeat::None, 0, 0, 'now', true];
		yield [NotifyRepeat::FrequencyRecords, 2, 2, 'now', true];
		yield [NotifyRepeat::FrequencyRecords, 2, 1, 'now', false];
		yield [NotifyRepeat::FrequencyRecords, 2, 0, 'now', false];
		yield [NotifyRepeat::PeriodTime, 60, 0, '-1 hours', true];
		yield [NotifyRepeat::PeriodTime, 65, 0, '-1 hours', true];
		yield [NotifyRepeat::PeriodTime, 60, 0, 'now', false];
	}
}
