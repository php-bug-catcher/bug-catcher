<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 7. 6. 2024
 * Time: 18:55
 */
namespace BugCatcher\Enum;

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

    /**
     * Determines if this importance is higher than the given importance.
     * @param Importance $importance
     * @return bool
     */
    public function isHigherThan(Importance $importance): bool
    {
		foreach (self::all() as $i) {
            if ($i === $this) {
                return false;
            }
            if ($i === $importance) {
                return true;
			}
		}
	}

	/**
	 * Determines if this importance is lover or equal to the given importance.
	 *
	 * @param Importance $importance
	 * @return bool
	 */
    public function isHigherOrEqualThan(Importance $importance): bool
    {
        return $this === $importance || $this->isHigherThan($importance);
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

	public function lower(): Importance {
		return match ($this) {
			self::Medium => self::Low,
			self::High => self::Medium,
			self::Critical => self::High,
			self::MamaMia => self::Critical,
			default => self::Normal,
		};
	}


	public function higher(): Importance {
		return match ($this) {
            self::Normal => self::Low,
			self::Low => self::Medium,
			self::Medium => self::High,
			self::High => self::Critical,
			self::Critical => self::MamaMia,
			default => self::Normal,
		};
	}
}