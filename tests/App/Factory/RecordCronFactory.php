<?php

namespace BugCatcher\Tests\App\Factory;

use BugCatcher\Tests\App\Entity\RecordCron;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * A record type that is not a `RecordLog`, which is what the MCP tools are exercised against.
 *
 * @extends PersistentProxyObjectFactory<RecordCron>
 */
final class RecordCronFactory extends PersistentProxyObjectFactory {
	public static function class(): string {
		return RecordCron::class;
	}

	protected function defaults(): array|callable {
		return [
			'date'      => new DateTimeImmutable(),
			'project'   => ProjectFactory::new(),
			'status'    => 'new',
			'command'   => 'app:' . self::faker()->slug(2),
			'lastStart' => new DateTimeImmutable('-10 minutes'),
			'lastEnd'   => new DateTimeImmutable('-9 minutes'),
			'estimated' => 120,
			// the property is named `_interval` because `interval` is what the JSON key is; Foundry
			// force-sets by property name, so this is the key it wants
			'_interval' => 60,
		];
	}

	protected function initialize(): static {
		// the hash is what groups the runs of one command, and nothing computes it on its own outside
		// the ingest processor
		return $this->afterInstantiate(
			static fn(RecordCron $record) => $record->setHash($record->calculateHash()),
		);
	}
}
