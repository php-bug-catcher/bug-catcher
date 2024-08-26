<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 14. 7. 2024
 * Time: 21:06
 */
namespace BugCatcher\Service;

use BugCatcher\Entity\RecordLog;
use BugCatcher\Entity\RecordLogWithholder as RecordLogWithholderEntity;
use BugCatcher\Repository\RecordLogRepository;
use BugCatcher\Repository\RecordLogWithholderRepository;

readonly final class RecordLogWithholder
{


	public function __construct(
		private RecordLogRepository           $logRepo,
		private RecordLogWithholderRepository $withholderRepo,
		private Transaction                   $transaction,
	) {}

	public function process(RecordLog $log): void {
		$withholders = $this->withholderRepo->findBy(["project" => $log->getProject()]);
		foreach ($withholders as $withholder) {
			$regex = $withholder->getRegex();
			if (!preg_match($regex, $log->getMessage())) {
				continue;
			}
			$status         = "withheld-{$withholder->getId()}";
			$withholderLogs = $this->logRepo->findBy([
				"hash"    => $log->calculateHash(),
				"status"  => [$status, "new"],
				"project" => $log->getProject(),
			], ["date" => "DESC"]);
			array_unshift($withholderLogs, $log);
			$log->setStatus($status);
			$start = $log->getDate()->getTimestamp();
			$count = 0;
			for ($i = 0; $i < count($withholderLogs); $i++) {
				$log  = $withholderLogs[$i];
				$end  = $log->getDate()->getTimestamp();
				$diff = $start - $end;
				if ($diff <= $withholder->getThresholdInterval()) {
					$count++;
					if ($count > $withholder->getThreshold() && $status !== "new") {
						$status = "new";
						$i      = -1;
						continue;
					}
					$log->setStatus($status);
				} else {
					$log->setStatus($status == "new" ? "new" : "resolved-{$withholder->getId()}");
				}
			}
		}
		$this->transaction->flush();
	}
}