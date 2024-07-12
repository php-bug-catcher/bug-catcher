<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 18:55
 */
namespace PhpSentinel\BugCatcher\Enum;

enum Importance: string {
	case Normal   = 'normal';
	case Low      = 'low';
	case Medium   = 'medium';
	case High     = 'high';
	case Critical = 'critical';
	case MamaMia  = 'mama-mia';

	/** @return array<Importance> */
	public static function all(): array {
		return [
			self::Normal,
			self::Low,
			self::Medium,
			self::High,
			self::Critical,
			self::MamaMia,
		];
	}

	public function isHigher(Importance $importance): bool {
		foreach (self::all() as $i) {
			if ($i === $importance) {
				return false;
			}
			if ($i === $this) {
				return true;
			}
		}
	}

	public function isHigherOrEqual(Importance $importance): bool {
		return $this === $importance || $this->isHigher($importance);
	}

	public static function min(): Importance {
		return self::Normal;
	}

	public static function max(): Importance {
		return self::MamaMia;
	}

	public function getColor(): BootstrapColor {
		return match ($this) {
			self::Low => BootstrapColor::Secondary,
			self::Medium => BootstrapColor::Warning,
			self::High => BootstrapColor::Primary,
			self::Critical => BootstrapColor::Info,
			self::MamaMia => BootstrapColor::Danger,
			default => BootstrapColor::Default,
		};
	}
}