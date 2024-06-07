<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 18:55
 */
namespace PhpSentinel\BugCatcher\Enum;

enum Importance: int {
	case Normal   = 0;
	case Low      = 1;
	case Medium   = 2;
	case High     = 3;
	case Critical = 4;
	case MamaMia  = 5;

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

	public static function min(): Importance {
		return self::Normal;
	}

	public static function max(): Importance {
		return self::MamaMia;
	}

	public function getColor(): BootstrapColor {
		return match ($this) {
			self::Low => BootstrapColor::Info,
			self::Medium => BootstrapColor::Secondary,
			self::High => BootstrapColor::Primary,
			self::Critical => BootstrapColor::Warning,
			self::MamaMia => BootstrapColor::Danger,
			default => BootstrapColor::Default,
		};
	}
}