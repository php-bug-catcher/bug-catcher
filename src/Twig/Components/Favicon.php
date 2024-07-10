<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 18:00
 */
namespace PhpSentinel\BugCatcher\Twig\Components;

use PhpSentinel\BugCatcher\Enum\BootstrapColor;
use PhpSentinel\BugCatcher\Service\DashboardStatus;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Favicon {

	public string $id = "";

	public function __construct(
		private readonly Packages      $assetManager,
		private readonly DashboardStatus $status,
		#[Autowire(param: 'logo')]
		private readonly string $logo
	) {
		$this->id = uniqid();
	}


	public function getIcon(): string {
		$color = $this->status->getImportance()->getColor()->value;

		return $this->assetManager->getUrl("/assets/logo/{$this->logo}/icon-{$color}.svg", 'bug_catcher');
	}

}