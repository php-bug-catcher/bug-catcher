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
use PhpSentinel\BugCatcher\Repository\NotifierRepository;
use PhpSentinel\BugCatcher\Repository\RecordPingRepository;
use PhpSentinel\BugCatcher\Service\DashboardStatus;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class WarningSound extends AbsComponent  {

	public function __construct(
		private readonly RecordPingRepository $recordRepo,
		private readonly NotifierRepository $notifierRepo
	) {}


	public function getSound(): ?string {
		if ($this->project->getPingCollector() == 'none') {
			return null;
		}
		$ping = $this->recordRepo->findOneBy([
			"project" => $this->project,
		], [
			"date" => "DESC",
		]);

		$state = $ping?->getStatusCode() == Response::HTTP_OK;
		/** @var NotifierSound|false $soundNotifiers */
		$soundNotifiers = $this->project->findNotifiers(NotifierSound::class, Importance::Low)->first();
		if ($soundNotifiers) {
			if (!$state && $this->notifierRepo->shouldNotify($soundNotifiers, false)) {
				return '/uploads/sound/'.$soundNotifiers->getFile();
			} else {
				$this->notifierRepo->stopNotify($soundNotifiers);
			}
		}
		return null;

	}
}