<?php

namespace BugCatcher\Controller;

use Exception;
use BugCatcher\Entity\Record;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

final class DashboardController extends AbstractController
{


	public function __construct(
		private readonly array $classesComponents,
		private readonly array $components,
		private readonly int   $refreshInterval,
	) {}

	public function index(
		string $status = 'new'
	): Response {
		return $this->render('@BugCatcher/dashboard/index.html.twig', [
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
