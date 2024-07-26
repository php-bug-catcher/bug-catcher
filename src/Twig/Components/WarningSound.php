<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 18:00
 */
namespace PhpSentinel\BugCatcher\Twig\Components;

use PhpSentinel\BugCatcher\Entity\NotifierSound;
use PhpSentinel\BugCatcher\Enum\Importance;
use PhpSentinel\BugCatcher\Repository\NotifierRepository;
use PhpSentinel\BugCatcher\Repository\RecordPingRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class WarningSound extends AbsComponent  {

	public string $id;

	public function __construct(
		private readonly RecordPingRepository $recordRepo,
		private readonly NotifierRepository $notifierRepo
	) {
		$this->id = Uuid::v6()->toRfc4122();
	}


	public function getSound(): ?string {
		if ($this->project->getPingCollector() == 'none') {
			return null;
		}
		$ping = $this->recordRepo->getLastRecord($this->project);

		$state = $ping?->getStatusCode() == Response::HTTP_OK;
		/** @var NotifierSound|false $soundNotifiers */
		$soundNotifiers = $this->project->findNotifiers(NotifierSound::class, Importance::Low)->first();
		if ($soundNotifiers) {
			if (!$state) {
				if ($this->notifierRepo->shouldNotify($soundNotifiers, false)) {
					return '/uploads/sound/' . $soundNotifiers->getFile();
				}
			} else {
//				$this->notifierRepo->stopNotify($soundNotifiers);
			}
		}
		return null;

	}
}