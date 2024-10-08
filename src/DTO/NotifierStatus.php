<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 18:07
 */
namespace BugCatcher\DTO;

use BugCatcher\Entity\Project;
use BugCatcher\Enum\Importance;

final class NotifierStatus
{

	/** @var array<Importance, Importance> */
	private array $importances = [];
	private array $increments = [];

	/**
	 * @param Importance[] $importances
	 */
	public function __construct(
		public readonly Project $project
	) {}


	public function levelUp(Importance $group): void {
		if (!array_key_exists($group->value, $this->importances)) {
			$this->importances[$group->value] = Importance::min();
		}
		$all = Importance::all();
		foreach ($all as $key => $value) {
			if ($value === $this->importances[$group->value] && $key < count($all) - 1) {
				$this->importances[$group->value] = $all[$key + 1];

				return;
			}
		}
	}

	public function incrementImportance(Importance $group, int $importance, int $topImportance = 10): void {
		if (!$importance) {
			return;
		}
		if (!array_key_exists($group->value, $this->increments)) {
			$this->increments[$group->value] = 0;
		}
		$max = count(Importance::all()) - 1;
		$ratio            = $max / $topImportance;
		$newImportance = $importance * $ratio;
		$newImportance = $this->increments[$group->value] += $newImportance;
		$this->setImportance($group, $newImportance, $max);
	}

	public function setImportance(Importance $group, float $importance, float $topImportance = 10): void {
		$max                              = count(Importance::all()) - 1;
		$ratio                            = $max / $topImportance;
		$importance                       = $importance * $ratio;
		$importance                       = min($importance, $max);
		$importance                       = max($importance, 1);
		$importance                       = round($importance);
		$this->importances[$group->value] = Importance::all()[$importance];
	}

	public function ok(Importance $group): void {
        $this->importances[$group->value] = Importance::min();
	}

	public function danger(Importance $group): void {
        $this->importances[$group->value] = Importance::max();
	}

	public function getImportance(): Importance {
        $all = array_map(fn(Importance $importance) => $importance->value, Importance::all());
        $importances = $this->importances;
        uksort($importances, function ($a, $b) use ($all) {
			return array_search($b, $all) <=> array_search($a, $all);
		});
        if ($importance = current($importances)) {
            return $importance;
        }
        return Importance::Normal;
	}
}