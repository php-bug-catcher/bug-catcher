<?php

namespace BugCatcher\Twig\Components;

use BugCatcher\Service\SparkLine\SparkLineGapMode;
use BugCatcher\Service\SparkLine\SparkLineRenderer;
use BugCatcher\Service\SparkLine\SparkLineScale;
use BugCatcher\Service\SparkLine\SparkLineSlots;
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

	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly SparkLineSlots $slots,
		private readonly SparkLineRenderer $renderer,
	) {}

	public function getSparkLine(): string {
		// `treshold` is the top of the scale, which is what makes the red band mean something:
		// at the threshold the line is in --bc-spark-high, below it is not. Anything above clamps
		// there - a sparkline this size cannot say "ten times over" and pretending otherwise is
		// what used to push the line out of the SVG entirely.
		return $this->renderer->render(
			$this->getSlotValues(),
			new SparkLineScale((float)$this->treshold),
		);
	}

	/** How many points the line has: one per `minutes` across `graphHours`. */
	public function slotCount(): int {
		return (int)ceil($this->graphHours * 60 / $this->minutes);
	}

	/**
	 * The error count of each interval, oldest first, one value per slot.
	 *
	 * The buckets trail `now` rather than sitting on the wall clock: slot `n` is the `minutes`
	 * that ended `(slots - 1 - n) * minutes` ago, so the last one is always a *whole* interval
	 * ending this second. That is what the chart used to get wrong - it drew either a bucket that
	 * was still filling up or, past the halfway point of an interval, one in the future that no
	 * row could ever land in. Either way the line dived to the floor at the right edge and the
	 * shape depended on what minute you looked at it.
	 *
	 * Letting the database do the bucketing by slot index, and not by a formatted timestamp two
	 * code paths have to agree on, is what makes that impossible rather than fixed.
	 *
	 * @return list<float>
	 * @throws Exception
	 */
	public function getSlotValues(): array {
		$slots    = $this->slotCount();
		$interval = $this->minutes * 60;
		$from     = new DateTimeImmutable("-" . ($slots * $interval) . " seconds");

		$sql = <<<SQL
select floor(timestampdiff(second, :from, r.date) / :interval) as slot, count(*) as cnt
from record_log
join record r on r.id = record_log.id
where r.project_id = :project and r.date > :from
group by slot
order by slot
SQL;

		$stm = $this->em->getConnection()->prepare($sql);
		$stm->bindValue("project", $this->project->getId(), UuidType::NAME);
		$stm->bindValue("from", $from->format("Y-m-d H:i:s"));
		$stm->bindValue("interval", $interval, ParameterType::INTEGER);

		$bySlot = [];
		foreach ($stm->executeQuery()->fetchAllAssociative() as $row) {
			// A record dated in the future - a client with a skewed clock - belongs in the newest
			// slot rather than nowhere. An error that silently vanishes off a wall monitor is the
			// worse of the two lies.
			$slot          = min((int)$row["slot"], $slots - 1);
			$bySlot[$slot] = ($bySlot[$slot] ?? 0) + (int)$row["cnt"];
		}

		// An interval nobody logged an error in had zero errors. It draws a line along the
		// baseline, which is the shape a quiet project is supposed to have.
		return $this->slots->fill($bySlot, $slots, SparkLineGapMode::Zero);
	}
}
