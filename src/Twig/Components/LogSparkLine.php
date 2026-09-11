<?php

namespace BugCatcher\Twig\Components;

use Brendt\SparkLine\Period;
use Brendt\SparkLine\SparkLine;
use Brendt\SparkLine\SparkLineInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class LogSparkLine extends AbsComponent {
	public int $minutes = 15;
	public int $treshold = 5;
	public int $graphHours = 24;

	/** Drawing surface of the generated SVG. The rendered size is CSS's job - see stretchToContainer(). */
	private const WIDTH  = 250;
	private const HEIGHT = 30;

	public function __construct(
		private readonly EntityManagerInterface $em
	) {}

	public function getSparkLine(): string {
		$indexed   = $this->getSparkLineIntervals();
		$sparkLine = SparkLine::new(collect($indexed), Period::MINUTE, $this->minutes)
			->withMaxItemAmount(($this->graphHours * 60) / $this->minutes)
			->withDimensions(self::WIDTH, self::HEIGHT)
			->withMaxValue($this->treshold)
			// CSS variables rather than hex: the SVG is inlined into the page, so the
			// gradient stops resolve against the active theme. See --bc-spark-* in app.css.
			->withColors('var(--bc-spark-low)', 'var(--bc-spark-mid)', 'var(--bc-spark-high)');

		return $this->stretchToContainer($sparkLine->make());
	}

	/**
	 * The library writes a fixed width onto the <svg>, so the chart stays 250px wide inside a
	 * project card that is rarely 250px wide. Trading that width for a viewBox hands sizing to
	 * CSS; preserveAspectRatio="none" is what lets a sparkline stretch to fill rather than
	 * letterbox, which is the right trade here because the shape carries the trend, not a scale.
	 */
	private function stretchToContainer(string $svg): string {
		return preg_replace(
			'/^<svg width="(\d+)" height="(\d+)"/',
			'<svg viewBox="0 0 $1 $2" preserveAspectRatio="none" height="$2"',
			$svg,
			1
		);
	}

	/**
	 * @return SparkLineInterval[]
	 * @throws Exception
	 */
	public function getSparkLineIntervals(): array {
		$maxDate = new DateTimeImmutable("-{$this->graphHours} hours");
		$sql     = <<<SQL
select
    count(*) as cnt ,
    concat(DATE_FORMAT(`date`,'%Y-%c-%d %H:'),TIME_FORMAT(SEC_TO_TIME(((DATE_FORMAT(`date`,'%i') div {$this->minutes})*{$this->minutes})*60),'%i'),':00') as period
from record_log
join record r on r.id = record_log.id
where project_id=:project and `date` > :date
group by period
order by period
SQL;
		$stm     = $this->em->getConnection()
			->prepare($sql);
		$stm->bindValue("project", $this->project->getId(), UuidType::NAME);
		$stm->bindValue("date", $maxDate->format("Y-m-d H:i:s"));
		$rows    = $stm->executeQuery()->fetchAllAssociative();
		$indexed = array_map(fn(array $row) => new SparkLineInterval($row["cnt"], new DateTimeImmutable($row["period"])), $rows);
//		array_unshift($indexed, new SparkLineInterval(self::TRESHOLD, new \DateTimeImmutable("-" . (self::GRAPH_HOURS + 1) . "hour")));
		return $indexed;
	}
}
