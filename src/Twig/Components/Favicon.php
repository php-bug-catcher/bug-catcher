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
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Favicon {

	public function __construct(
		private readonly Packages      $assetManager,
		private readonly DashboardStatus $status
	) {}


	public function getIcon(): string {
		$color = $this->status->getImportance()->getColor()->value;

		return $this->assetManager->getUrl("/assets/logo/icon-{$color}.svg", 'bug_catcher');
	}
}