<?php

namespace PhpSentinel\BugCatcher\Factory;

use PhpSentinel\BugCatcher\Entity\NotifierSound;
use PhpSentinel\BugCatcher\Enum\Importance;
use PhpSentinel\BugCatcher\Enum\NotifyRepeat;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<NotifierSound>
 */
final class NotifierSoundFactory extends PersistentProxyObjectFactory {
	/**
	 * @see  https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
	 *
	 * @todo inject services if required
	 */
	public function __construct() {}

	public static function class(): string {
		return NotifierSound::class;
	}

	/**
	 * @see  https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
	 *
	 * @todo add your default values here
	 */
	protected function defaults(): array|callable {
		return [
			'clearAt'           => self::faker()->randomElement(NotifyRepeat::cases()),
			'delay'             => self::faker()->randomElement(NotifyRepeat::cases()),
			'failedStatusCount' => self::faker()->randomNumber(),
			'file'              => self::faker()->text(255),
			'lastOkStatusCount' => self::faker()->randomNumber(),
			'minimalImportance' => self::faker()->randomElement(Importance::cases()),
			'name'              => self::faker()->text(255),
			'repeat'            => self::faker()->randomElement(NotifyRepeat::cases()),
		];
	}

	/**
	 * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
	 */
	protected function initialize(): static {
		return $this// ->afterInstantiate(function(NotifierSound $notifierSound): void {})
			;
	}
}
