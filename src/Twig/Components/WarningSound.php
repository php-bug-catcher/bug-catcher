<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 18:00
 */
namespace BugCatcher\Twig\Components;

use BugCatcher\Entity\NotifierSound;
use BugCatcher\Service\DashboardImportance;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class WarningSound extends AbsComponent
{

    use \BugCatcher\Twig\Components\DashboardImportance;
	public string $id;

	public function __construct(
        private readonly DashboardImportance $importance,
        private readonly Security $security,
	) {
		$this->id = Uuid::v6()->toRfc4122();
	}


	public function getSound(): ?string {
		/** @var NotifierSound $notifier */
        [$importance, $notifier] = $this->getMaxImportance();
        if (!$notifier) {
			return null;
		}

		if ($importance->isHigher($notifier->getMinimalImportance())) {
			return '/uploads/sound/' . $notifier->getFile();
		}
		return null;

	}
}