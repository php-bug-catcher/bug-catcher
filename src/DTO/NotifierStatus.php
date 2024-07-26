<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 18:07
 */
namespace PhpSentinel\BugCatcher\DTO;

use PhpSentinel\BugCatcher\Enum\BootstrapColor;
use PhpSentinel\BugCatcher\Enum\Importance;

class NotifierStatus {

	/** @var array<Importance, Importance> */
	private array $importances = [];
	private array $increments = [];

	public function levelUp(Importance $group): void {
		if (!array_key_exists($group->value, $this->importances)) {
			$this->importances[$group->value] = Importance::min();

			return;
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
		$this->importances[$group->value] = BootstrapColor::Success;
	}

	public function danger(Importance $group): void {
		$this->importances[$group->value] = BootstrapColor::Danger;
	}

	public function getImportance(): Importance {
		$all = Importance::all();
		usort($this->importances, function (Importance $a, Importance $b) use ($all) {
			return array_search($b, $all) <=> array_search($a, $all);
		});
		return $this->importances[0]??Importance::Normal;
	}
}