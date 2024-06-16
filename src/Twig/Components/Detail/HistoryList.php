<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 1. 6. 2024
 * Time: 7:11
 */
namespace PhpSentinel\BugCatcher\Twig\Components\Detail;

use PhpSentinel\BugCatcher\Entity\Record;
use PhpSentinel\BugCatcher\Repository\RecordRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(template: '@BugCatcher/components/Detail/HistoryList.html.twig')]
class HistoryList {
	public Record $record;

	public function __construct(private readonly RecordRepository $recordRepo) {}

	public function getHistory(): array {
		return $this->recordRepo->findBy([
			"",
		]);
	}
}