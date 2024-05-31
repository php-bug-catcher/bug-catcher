<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 24. 5. 2024
 * Time: 11:33
 */
namespace PhpSentinel\BugCatcher\Service\PingCollector;

use PhpSentinel\BugCatcher\Entity\Project;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;

class MessengerCollector implements PingCollectorInterface {


	public function __construct(
		private readonly ManagerRegistry $registry
	) {}

	public function ping(Project $project): string {
		if ($connection = $project->getDbConnection()) {
			/** @var Connection $conn */
			$conn = $this->registry->getConnection($connection);

			return $this->checkMessengerFirstMessage($conn) ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;
		}

		return Response::HTTP_NOT_FOUND;
	}

	private function checkMessengerFirstMessage(Connection $conn): bool {
		$res = $conn->executeQuery("select created_at from messenger_messages order by created_at asc limit 1;")->fetchAssociative();
		if ($res && $res['created_at']) {
			return (strtotime($res['created_at']) > strtotime('-5 minutes'));
		}

		return true;
	}
}