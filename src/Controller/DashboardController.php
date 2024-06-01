<?php

namespace PhpSentinel\BugCatcher\Controller;

use Exception;
use PhpSentinel\BugCatcher\Entity\Record;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractController {


	public function index(
		#[Autowire(param: 'dashboard_components')]
		array  $components,
		string $status = 'new'
	): Response {
		return $this->render('@BugCatcher/dashboard/index.html.twig', [
			"status" => $status,
			"components" => $components,
		]);
	}

	public function detail(
		#[Autowire(param: 'detail_components')]
		array  $classesComponents,
		Record $record
	): Response {
		foreach ($classesComponents as $class => $components) {
			if ($record instanceof $class) {
				return $this->render('@BugCatcher/dashboard/detail.html.twig', [
					"record"     => $record,
					"components" => $components,
				]);
			}
		}
		throw new Exception("No detail components definition found for record type. See " .
			"'https://github.com/php-sentinel/bug-catcher/blob/main/docs/extending.md#detail-page-components'");
	}

}
