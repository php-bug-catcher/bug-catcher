<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 18:00
 */
namespace PhpSentinel\BugCatcher\Twig\Components;

use PhpSentinel\BugCatcher\Service\DashboardStatus;
use Symfony\Component\Asset\Packages;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class WarningSound {

	public function __construct(
		private readonly Packages        $assetManager,
		private readonly DashboardStatus $status
	) {}


	public function getIcon(): string {
		$name = $this->status->getImportance()->value;

		return $this->assetManager->getUrl("/images/logo/icon-{$color}.svg", 'bug_catcher');
	}
}