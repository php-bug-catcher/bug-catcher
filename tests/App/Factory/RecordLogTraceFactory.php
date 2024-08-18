<?php

namespace BugCatcher\Tests\App\Factory;

use BugCatcher\Entity\RecordLogTrace;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<RecordLogTrace>
 */
final class RecordLogTraceFactory extends PersistentProxyObjectFactory {
	/**
	 * @see  https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
	 *
	 * @todo inject services if required
	 */
	public function __construct() {}

	public static function class(): string {
		return RecordLogTrace::class;
	}

	/**
	 * @see  https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
	 *
	 * @todo add your default values here
	 */
	protected function defaults(): array|callable {
		return [
			'date'       => self::faker()->dateTime(),
			'level'      => self::faker()->randomNumber(),
			'message'    => self::faker()->text(),
			'project'    => ProjectFactory::new(),
			'requestUri' => self::faker()->text(1500),
			'status'     => self::faker()->text(50),
		];
	}

	/**
	 * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
	 */
	protected function initialize(): static {
		return $this// ->afterInstantiate(function(RecordLogTrace $recordLogTrace): void {})
			;
	}
}
