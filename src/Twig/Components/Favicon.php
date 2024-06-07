<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 18:00
 */
namespace PhpSentinel\BugCatcher\Twig\Components;

use PhpSentinel\BugCatcher\Enum\BootstrapColor;
use PhpSentinel\BugCatcher\Service\FaviconStatus;
use Symfony\Component\Asset\Packages;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Favicon {

	public function __construct(
		private readonly Packages      $assetManager,
		private readonly FaviconStatus $status
	) {}


	public function getIcon(): string {
		return $this->assetManager->getUrl("/images/logo/icon-{$this->status->getStatus()->value}.svg", 'bug_catcher');
	}
}