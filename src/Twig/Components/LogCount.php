<?php

namespace App\Twig\Components;

use App\Repository\LogRecordRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class LogCount extends AbsComponent
{


	public function __construct(
		private readonly LogRecordRepository $recordRepo
	) {}

	public function getCount():int {
		return $this->recordRepo->count([
			"project"=>$this->project,
			"checked"=>false
		]);
	}
}
