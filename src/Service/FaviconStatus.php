<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 18:07
 */
namespace PhpSentinel\BugCatcher\Service;

use PhpSentinel\BugCatcher\Enum\BootstrapColor;

class FaviconStatus {

	/** @var array<int, BootstrapColor> */
	private array $colors = [];
	private float $importance = 0;

	private array $importanceColors = [
		BootstrapColor::Info,
		BootstrapColor::Secondary,
		BootstrapColor::Primary,
		BootstrapColor::Warning,
		BootstrapColor::Danger,
	];

	public function levelUp(int $priority): void {
		if (!array_key_exists($priority, $this->colors)) {
			$this->colors[$priority] = $this->importanceColors[0];

			return;
		}
		foreach ($this->importanceColors as $key => $value) {
			if ($value === $this->colors[$priority] && $key < count($this->importanceColors) - 1) {
				$this->colors[$priority] = $this->importanceColors[$key + 1];

				return;
			}
		}
	}

	public function incrementImportance(int $priority, int $importance, int $topImportance = 10): void {
		if (!$importance) {
			return;
		}
		$max              = count($this->importanceColors) - 1;
		$ratio            = $max / $topImportance;
		$importance       = $importance * $ratio;
		$this->importance += $importance;
		$this->setImportance($priority, $this->importance, $max);
	}

	public function setImportance(int $priority, float $importance, float $topImportance = 10): void {
		$max                     = count($this->importanceColors) - 1;
		$ratio                   = $max / $topImportance;
		$importance              = $importance * $ratio;
		$importance              = min($importance, $max);
		$importance              = max($importance, 1);
		$importance              = round($importance);
		$this->colors[$priority] = $this->importanceColors[$importance];
	}

	public function ok(int $priority): void {
		$this->colors[$priority] = BootstrapColor::Success;
	}

	public function danger(int $priority): void {
		$this->colors[$priority] = BootstrapColor::Danger;
	}

	public function getStatus(): BootstrapColor {
		usort($this->colors, function (BootstrapColor $a, BootstrapColor $b) {
			return array_search($b, $this->importanceColors) <=> array_search($a, $this->importanceColors);
		});

		return $this->colors[0]??BootstrapColor::Default;
	}
}