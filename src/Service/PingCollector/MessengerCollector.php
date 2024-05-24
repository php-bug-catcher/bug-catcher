<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 24. 5. 2024
 * Time: 11:33
 */
namespace App\Service\PingCollector;

use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;

class MessengerCollector implements PingCollectorInterface {


	public function __construct(
		private readonly ManagerRegistry $registry
	) {}

	public function ping(Project $project): string {
		if ($connection = $project->getDbConnection()) {
			$em = $this->registry->getConnection($connection);

			return $this->checkMessengerFirstMessage($em) ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;
		}

		return Response::HTTP_NOT_FOUND;
	}

	private function checkMessengerFirstMessage(EntityManagerInterface $em): bool {
		$res = $em->getConnection()->executeQuery("select created_at from messenger_messages order by created_at asc limit 1;")->fetchAssociative();
		if ($res && $res['created_at']) {
			return (strtotime($res['created_at']) > strtotime('-5 minutes'));
		}

		return true;
	}
}