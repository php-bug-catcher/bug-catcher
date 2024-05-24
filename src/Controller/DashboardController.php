<?php

namespace App\Controller;

use App\Entity\LogRecord;
use App\Repository\ProjectRepository;
use Kregel\ExceptionProbe\Stacktrace;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{


	#[Route('/', name: 'app.dashboard')]
    public function index(ProjectRepository $projectRepo): Response
    {
        return $this->render('dashboard/index.html.twig',[
			"projects" => $projectRepo->findAll(),
		]);
    }

	#[Route('/detail/{record}', name: 'app.dashboard.detail')]
	public function detail(LogRecord $record): Response {
		$stacktrace = unserialize($record->getStacktrace());

		return $this->render('dashboard/detail.html.twig', [
			"record"     => $record,
			"stacktrace" => $stacktrace,
		]);

	}

}
