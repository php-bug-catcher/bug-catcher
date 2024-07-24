<?php

namespace PhpSentinel\BugCatcher\Twig\Components;

use Brendt\SparkLine\Period;
use Brendt\SparkLine\SparkLine;
use Brendt\SparkLine\SparkLineInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class LogSparkLine extends AbsComponent {
	public int $minutes = 15;
	public int $treshold = 5;
	public int $graphHours = 24;

	public function __construct(
		private readonly EntityManagerInterface $em
	) {}

	public function getSparkLine() {
		$maxDate = new DateTimeImmutable("-{$this->graphHours} hours");
		$sql     = <<<SQL
select
    count(*) as cnt ,
    concat(DATE_FORMAT(`date`,'%Y-%c-%d %H:'),TIME_FORMAT(SEC_TO_TIME(((DATE_FORMAT(`date`,'%i') div {$this->minutes})*{$this->minutes})*60),'%i'),':00') as period
from record_log
join record r on r.id = record_log.id
where project_id=? and `date` > ?
group by period
SQL;
		$rows    = $this->em->getConnection()
			->executeQuery($sql, [
				$this->project->getId()->toHex(),
				$maxDate->format("Y-m-d H:i:s"),
			])
			->fetchAllAssociative();
		$indexed = array_map(fn(array $row) => new SparkLineInterval($row["cnt"], new DateTimeImmutable($row["period"])), $rows);
//		array_unshift($indexed, new SparkLineInterval(self::TRESHOLD, new \DateTimeImmutable("-" . (self::GRAPH_HOURS + 1) . "hour")));
		$sparkLine = SparkLine::new(collect($indexed), Period::MINUTE, $this->minutes)
			->withMaxItemAmount(($this->graphHours * 60) / $this->minutes)
			->withDimensions(250, 30)
			->withMaxValue($this->treshold)
			->withColors('#4fae00', '#0857fd', '#ff0000');

		return $sparkLine->make();
	}
}
