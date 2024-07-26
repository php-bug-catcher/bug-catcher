<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 18:00
 */
namespace PhpSentinel\BugCatcher\Twig\Components;

use PhpSentinel\BugCatcher\Entity\NotifierSound;
use PhpSentinel\BugCatcher\Service\DashboardImportance;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class WarningSound extends AbsComponent  {

	public string $id;

	public function __construct(
		private readonly DashboardImportance $importance,
	) {
		$this->id = Uuid::v6()->toRfc4122();
	}


	public function getSound(): ?string {
		/** @var NotifierSound $notifier */
		[$importance, $notifier] = $this->importance->load(NotifierSound::class);
		if (!$importance) {
			return null;
		}

		if ($importance->isHigher($notifier->getMinimalImportance())) {
			return '/uploads/sound/' . $notifier->getFile();
		}
		return null;

	}
}