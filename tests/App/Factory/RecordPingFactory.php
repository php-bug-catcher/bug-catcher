<?php

namespace PhpSentinel\BugCatcher\Tests\App\Factory;

use DateTimeImmutable;
use PhpSentinel\BugCatcher\Entity\RecordPing;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<RecordPing>
 */
final class RecordPingFactory extends PersistentProxyObjectFactory {
	/**
	 * @see  https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
	 *
	 * @todo inject services if required
	 */
	public function __construct() {}

	public static function class(): string {
		return RecordPing::class;
	}

	/**
	 * @see  https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
	 *
	 * @todo add your default values here
	 */
	protected function defaults(): array|callable {
		return [
			'date' => new DateTimeImmutable(self::faker()->dateTime()->format("Y-m-d H:i:s")),
			'project'    => ProjectFactory::new(),
			'status'     => self::faker()->text(50),
			'statusCode' => self::faker()->text(255),
		];
	}

	/**
	 * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
	 */
	protected function initialize(): static {
		return $this// ->afterInstantiate(function(RecordPing $recordPing): void {})
			;
	}
}
