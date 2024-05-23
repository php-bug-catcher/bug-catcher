<?php

namespace App\Controller;

use App\Repository\LogRecordRepository;
use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapDateTime;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{

	public function __construct(
		private readonly LogRecordRepository $recordRepo
	) {}

	#[Route('/', name: 'app.dashboard')]
    public function index(ProjectRepository $projectRepo): Response
    {
        return $this->render('dashboard/index.html.twig',[
			"projects" => $projectRepo->findAll(),
		]);
    }

}
