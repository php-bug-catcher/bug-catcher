<?php

namespace App\Controller;

use App\Entity\LogRecord;
use App\Entity\LogRecordStatus;
use App\Entity\Role;
use App\Repository\ProjectRepository;
use Kregel\ExceptionProbe\Stacktrace;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{


	#[Route('/{status}', name: 'app.dashboard')]
	public function index(ProjectRepository $projectRepo, LogRecordStatus $status = LogRecordStatus::NEW): Response
    {
        return $this->render('dashboard/index.html.twig',[
			"projects" => $projectRepo->findByAdmin($this->getUser()),
			"status" => $status,
		]);
    }

	#[Route('/detail/{record}', name: 'app.dashboard.detail')]
	#[IsGranted(Role::ROLE_DEVELOPER->value)]
	public function detail(LogRecord $record): Response {
		$stacktrace = unserialize($record->getStacktrace());

		return $this->render('dashboard/detail.html.twig', [
			"record"     => $record,
			"stacktrace" => $stacktrace,
		]);

	}

}
