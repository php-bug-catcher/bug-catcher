<?php

namespace PhpSentinel\BugCatcher\Tests\App\Factory;

use PhpSentinel\BugCatcher\Entity\RecordLogWithholder;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<RecordLogWithholder>
 */
final class RecordLogWithholderFactory extends PersistentProxyObjectFactory {
	/**
	 * @see  https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
	 *
	 * @todo inject services if required
	 */
	public function __construct() {}

	public static function class(): string {
		return RecordLogWithholder::class;
	}

	/**
	 * @see  https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
	 *
	 * @todo add your default values here
	 */
	protected function defaults(): array|callable {
		return [
			'name'    => self::faker()->text(255),
			'project' => ProjectFactory::new(),
			'regex'   => self::faker()->text(755),
		];
	}

	/**
	 * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
	 */
	protected function initialize(): static {
		return $this// ->afterInstantiate(function(RecordLogWithholder $recordLogWithholder): void {})
			;
	}
}
