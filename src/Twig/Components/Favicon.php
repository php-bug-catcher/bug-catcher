<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 18:00
 */
namespace BugCatcher\Twig\Components;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Favicon
{
    use DashboardImportance;
	public string $id = "";

	public function __construct(
        private readonly \BugCatcher\Service\DashboardImportance $importance,
		private readonly Packages $assetManager,
        private readonly Security $security,
		#[Autowire(param: 'logo')]
		private readonly string   $logo
	) {
		$this->id = uniqid();
	}


	public function getIcon(): string {

        [$importance, $notifier] = $this->getMaxImportance(NotifierFavicon::class);
        $color = $importance->getColor()->value;

		return $this->assetManager->getUrl("/assets/logo/{$this->logo}/icon-{$color}.svg", 'bug_catcher');
	}

}