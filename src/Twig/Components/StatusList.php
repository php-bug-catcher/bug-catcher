<?php

namespace BugCatcher\Twig\Components;

use BugCatcher\Controller\AbstractController;
use BugCatcher\Entity\Role;
use BugCatcher\Repository\ProjectRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsLiveComponent]
final class StatusList extends AbstractController {
	use DefaultActionTrait;
	public string $status = 'new';

	public function __construct(
		private readonly ProjectRepository $projectRepo,
		public readonly array              $components,

	) {}

	public function getProjects(): array {

		return $this->getUser()->getActiveProjects()->toArray();
	}
}
