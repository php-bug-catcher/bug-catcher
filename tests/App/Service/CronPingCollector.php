<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 28. 5. 2024
 * Time: 15:51
 */
namespace BugCatcher\Tests\App\Service;

use BugCatcher\Entity\Project;
use BugCatcher\Service\PingCollector\PingCollectorInterface;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;

class CronPingCollector implements PingCollectorInterface {
	private object $conn;

	public function __construct(
		private readonly ManagerRegistry $registry
	) {}

	public function ping(Project $project): string {
		return $this->checkCronLastRun() ? Response::HTTP_OK : Response::HTTP_NOT_FOUND;
	}

	private function checkCronLastRun(): bool {

		/** @var Connection $conn */
		$conn = $this->registry->getConnection("cron");
		$res  = $conn->executeQuery("select last_start from tasks_v2 where command='app:test-cron';")->fetchAssociative();
		if ($res && $res['last_start']) {
			return ($res['last_start'] > (new DateTime('-5 minutes'))->format('Y-m-d H:i:s'));
		}

		return false;
	}

}