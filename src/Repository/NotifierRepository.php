<?php

namespace PhpSentinel\BugCatcher\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PhpSentinel\BugCatcher\Entity\Notifier;
use PhpSentinel\BugCatcher\Enum\NotifyRepeat;

/**
 * @extends ServiceEntityRepository<Notifier>
 */
class NotifierRepository extends ServiceEntityRepository {
	public function __construct(ManagerRegistry $registry, string $entityClass = Notifier::class) {
		parent::__construct($registry, $entityClass);
	}


	public function stopNotify(Notifier $notifier): void {
		if ($this->canClear($notifier)) {
			$notifier->setLastFailedStatus(null);
			$notifier->setFailedStatusCount(0);
			$notifier->setFirstOkStatus(null);
			$notifier->setLastOkStatusCount(0);
		}
	}

	/**
	 * @param bool $status true if notification want to be sent
	 * @return bool true if notification should be sent
	 */
	public function shouldNotify(Notifier $notifier, bool $status): bool {
		if ($status) {
			$this->stopNotify($notifier);

			return false;
		} else {
			return $this->checkDelay($notifier) && $this->checkRepeat($notifier);
		}
	}

	public function canClear(Notifier $notifier): bool {
		switch ($notifier->getClearAt()) {
			case NotifyRepeat::None:
				return true;
			case NotifyRepeat::FrequencyRecords:
				if ($notifier->getLastOkStatusCount() >= $notifier->getClearInterval()) {
					return true;
				}
				$notifier->setLastOkStatusCount($notifier->getLastOkStatusCount() + 1);

				return false;
			case NotifyRepeat::PeriodTime:
				if ($notifier->getFirstOkStatus()?->getTimestamp() < time() - $notifier->getClearInterval()) {
					$notifier->setFirstOkStatus(null);

					return true;
				}
				if ($notifier->getFirstOkStatus() === null) {
					$notifier->setFirstOkStatus(new \DateTimeImmutable());
				}

				return false;
			default:
				throw new \InvalidArgumentException("Unknown NotifyRepeat type");
		}
	}

	public function checkRepeat(Notifier $notifier): bool {
		switch ($notifier->getRepeat()) {
			case NotifyRepeat::None:
				if ($notifier->getLastNotified()==null) {
					$notifier->setLastNotified(new \DateTimeImmutable());
					return true;
				}
				return false;
			case NotifyRepeat::PeriodTime:
				if ($notifier->getLastNotified() == null) {
					$notifier->setLastNotified(new \DateTimeImmutable());

					return true;
				}
				if ($notifier->getLastNotified()->getTimestamp() <= time() - $notifier->getRepeatInterval()) {
					$notifier->setLastNotified(new \DateTimeImmutable());

					return true;
				}

				return false;
			case NotifyRepeat::FrequencyRecords:
				if ($notifier->getRepeatAtSkipped()==0 || $notifier->getRepeatAtSkipped() >= $notifier->getRepeatInterval()) {
					$notifier->setRepeatAtSkipped(1);

					return true;
				}
				$notifier->setRepeatAtSkipped($notifier->getRepeatAtSkipped() + 1);

				return false;
			default:
				throw new \InvalidArgumentException("Unknown NotifyRepeat type");
		}
	}

	public function checkDelay(Notifier $notifier): bool {
		switch ($notifier->getDelay()) {
			case NotifyRepeat::None:
				return true;
			case NotifyRepeat::FrequencyRecords:
				if ($notifier->getFailedStatusCount() >= $notifier->getDelayInterval()) {
					return true;
				}
				$notifier->setFailedStatusCount($notifier->getFailedStatusCount() + 1);

				return false;
			case NotifyRepeat::PeriodTime:
				if ($notifier->getLastFailedStatus()?->getTimestamp() <= (new \DateTime())->getTimestamp() - $notifier->getDelayInterval()) {
					return true;
				}
				if ($notifier->getLastFailedStatus() === null) {
					$notifier->setLastFailedStatus(new \DateTimeImmutable());
				}

				return false;
			default:
				throw new \InvalidArgumentException("Unknown NotifyRepeat type");
		}
	}
}
