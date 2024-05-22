<?php

namespace App\Twig\Components;

use App\Entity\LogRecord;
use App\Repository\LogRecordRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class LogList
{
	public function __construct(
		private readonly LogRecordRepository $recordRepo
	) {}

	/**
	 * @return LogRecord[]
	 */
	public function getLogs(): array {
		return $this->recordRepo->createQueryBuilder('l')
			->addSelect('COUNT(l.id) as count')
			->where("l.checked = 0")
			->orderBy('l.date', 'DESC')
			->groupBy("l.message")
			->getQuery()
			->getResult();
	}
}
