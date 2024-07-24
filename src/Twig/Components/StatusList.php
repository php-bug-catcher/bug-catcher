<?php

namespace PhpSentinel\BugCatcher\Twig\Components;

use PhpSentinel\BugCatcher\Controller\AbstractController;
use PhpSentinel\BugCatcher\Entity\Role;
use PhpSentinel\BugCatcher\Repository\ProjectRepository;
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
		#[Autowire(param: 'status_list_components')]
		public readonly array              $components,

	) {}

	public function getProjects(): array {

		return $this->getUser()->getActiveProjects()->toArray();
	}
}
