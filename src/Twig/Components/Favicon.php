<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 18:00
 */
namespace BugCatcher\Twig\Components;

use BugCatcher\Entity\NotifierFavicon;
use BugCatcher\Entity\NotifierSound;
use BugCatcher\Enum\Importance;
use BugCatcher\Service\DashboardImportance;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Favicon
{

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
		[$importance, $notifier] = array_values($this->importance->load(NotifierFavicon::class));
		$color = Importance::min()->getColor()->value;
		if ($importance) {
			$color = $importance->getColor()->value;
		}

		return $this->assetManager->getUrl("/assets/logo/{$this->logo}/icon-{$color}.svg", 'bug_catcher');
	}

}