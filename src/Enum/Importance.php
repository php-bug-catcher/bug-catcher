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