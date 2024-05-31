<?php

namespace PhpSentinel\BugCatcher\Controller;

use PhpSentinel\BugCatcher\Entity\Record;
use PhpSentinel\BugCatcher\Entity\RecordStatus;
use PhpSentinel\BugCatcher\Entity\Role;
use PhpSentinel\BugCatcher\Repository\ProjectRepository;
use Kregel\ExceptionProbe\Stacktrace;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{


	public function index(ProjectRepository $projectRepo, RecordStatus $status = RecordStatus::NEW): Response
    {
        return $this->render('@BugCatcher/dashboard/index.html.twig',[
			"projects" => $projectRepo->findByAdmin($this->getUser()),
			"status" => $status,
		]);
    }

	public function detail(Record $record): Response {
		$stacktrace = unserialize($record->getStacktrace());

		return $this->render('@BugCatcher/dashboard/detail.html.twig', [
			"record"     => $record,
			"stacktrace" => $stacktrace,
		]);

	}

}
