<?php

namespace BugCatcher\Tests\App\Factory;

use BugCatcher\Entity\NotifierFavicon;
use BugCatcher\Enum\Importance;
use BugCatcher\Enum\NotifyRepeat;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<NotifierFavicon>
 */
final class NotifierFaviconFactory extends PersistentProxyObjectFactory {
	/**
	 * @see  https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
	 *
	 * @todo inject services if required
	 */
	public function __construct() {}

	public static function class(): string {
		return NotifierFavicon::class;
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
			'lastOkStatusCount' => self::faker()->randomNumber(),
			'minimalImportance' => self::faker()->randomElement(Importance::cases()),
			'name'              => self::faker()->text(255),
			'repeat'            => self::faker()->randomElement(NotifyRepeat::cases()),
			'threshold'         => self::faker()->randomNumber(),
		];
	}

	/**
	 * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
	 */
	protected function initialize(): static {
		return $this// ->afterInstantiate(function(NotifierFavicon $notifierFavicon): void {})
			;
	}
}
