<?php

namespace App\Twig\Components;

use App\Repository\PingRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class ProjectStatus extends AbsComponent
{

	public function __construct(
		private readonly PingRecordRepository $recordRepo
	) {}

	public function getLastStatus():string {
		$ping = $this->recordRepo->findOneBy([
			"project"=>$this->project
		],[
			"date"=>"DESC"
		]);

		return $ping?->getStatusCode() === 200;
	}
}
