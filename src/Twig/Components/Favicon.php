<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 18:00
 */
namespace PhpSentinel\BugCatcher\Twig\Components;

use PhpSentinel\BugCatcher\Entity\NotifierFavicon;
use PhpSentinel\BugCatcher\Entity\NotifierSound;
use PhpSentinel\BugCatcher\Enum\Importance;
use PhpSentinel\BugCatcher\Service\DashboardImportance;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Favicon {

	public string $id = "";

	public function __construct(
		private readonly DashboardImportance $importance,
		private readonly Packages $assetManager,
		#[Autowire(param: 'logo')]
		private readonly string   $logo
	) {
		$this->id = uniqid();
	}


	public function getIcon(): string {
		/** @var NotifierFavicon $notifier */
		[$importance, $notifier] = $this->importance->load(NotifierSound::class);
		$color = Importance::min()->value;
		if ($importance) {
			$color = $importance->value;
		}

		return $this->assetManager->getUrl("/assets/logo/{$this->logo}/icon-{$color}.svg", 'bug_catcher');
	}

}