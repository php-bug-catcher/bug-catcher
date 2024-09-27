<?php

namespace BugCatcher\Controller;

use BugCatcher\Entity\Project;
use BugCatcher\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use BugCatcher\Entity\Record;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Uid\Uuid;

final class DashboardController extends AbstractController
{


	public function __construct(
		private readonly array $classesComponents,
		private readonly array $components,
		private readonly int   $refreshInterval,
	) {}

	public function index(
        EntityManagerInterface $em,
        Request $request,
        #[MapQueryParameter]
        string $project = 'all',
        #[MapQueryParameter]
		string $status = 'new'
	): Response {
        if ($project == 'all') {
            $project = null;
        } else {
            $project = $em->getReference(Project::class, new Uuid($project));
        }
        $request->attributes->set('project', $project);
        $request->attributes->set('status', $status);

        return $this->render('@BugCatcher/dashboard/index.html.twig', [
            "project" => $project,
			"status" => $status,
			"components"      => $this->components,
			"refreshInterval" => $this->refreshInterval,
		]);
	}

	public function detail(
		Record $record
	): Response {
		foreach ($this->classesComponents as $class => $components) {
			if ($record instanceof $class) {
				return $this->render('@BugCatcher/dashboard/detail.html.twig', [
					"record"     => $record,
					"components" => $components,
				]);
			}
		}
		throw new Exception("No detail components definition found for record type. See " .
			"'https://github.com/php-bug-catcher/bug-catcher/blob/main/docs/extending.md#detail-page-components'");
	}

}
