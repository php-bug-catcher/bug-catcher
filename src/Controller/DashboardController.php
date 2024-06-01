<?php

namespace PhpSentinel\BugCatcher\Controller;

use PhpSentinel\BugCatcher\Entity\Record;
use PhpSentinel\BugCatcher\Entity\RecordStatus;
use PhpSentinel\BugCatcher\Entity\Role;
use PhpSentinel\BugCatcher\Repository\ProjectRepository;
use Kregel\ExceptionProbe\Stacktrace;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{


	public function index(
		#[Autowire(param: 'dashboard_components')]
		array  $components,
		string $status = 'new'
	): Response
    {
        return $this->render('@BugCatcher/dashboard/index.html.twig',[
			"status" => $status,
			"components" => $components,
		]);
    }

	public function detail(Record $record): Response {
		return $this->render('@BugCatcher/dashboard/detail.html.twig', [
			"record" => $record,
		]);

	}

}
