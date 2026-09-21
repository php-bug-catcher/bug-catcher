<?php

namespace BugCatcher\Mcp;

use BugCatcher\Entity\Record;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

/**
 * Which record types the MCP tools may read.
 *
 * `Record` is a hierarchy an application extends, and not every subtype belongs on an MCP server: a
 * `RecordPing` is the result of an uptime check, and it cannot even name its own component -
 * {@see \BugCatcher\Entity\RecordPing::getComponentName()} throws. So the tools work from the
 * allowlist in `bug_catcher.mcp.record_types` rather than from whatever the discriminator map
 * happens to hold.
 *
 * Kept apart from `dashboard_list_items` on purpose: what a human triages on the dashboard and what
 * an AI holding the MCP token may resolve are two decisions, and conflating them would hand a new
 * custom record type to the MCP server as a side effect of putting it on a page.
 */
final class RecordTypes
{
	/**
	 * discriminator value => class, or null until the mapping has been read
	 *
	 * @var array<string, class-string<Record>>|null
	 */
	private ?array $resolved = null;

	/**
	 * @param list<class-string<Record>> $classes
	 */
	public function __construct(
		private readonly EntityManagerInterface $em,
		private readonly array                  $classes,
	) {}

	/**
	 * The discriminator values to search, which is what DQL's `INSTANCE OF` binds.
	 *
	 * Never empty: an empty list would compile to `discr IN ()`, which is not SQL at all. The
	 * configuration refuses an empty value, so this cannot be reached with one.
	 *
	 * @return list<string>
	 */
	public function discriminators(): array {
		return array_keys($this->resolve());
	}

	/**
	 * Whether a record that is already in hand is one of the searchable types.
	 */
	public function allows(Record $record): bool {
		$discriminator = $this->em->getClassMetadata($record::class)->discriminatorValue;

		return isset($this->resolve()[$discriminator]);
	}

	/**
	 * @return array<string, class-string<Record>>
	 */
	private function resolve(): array {
		if ($this->resolved !== null) {
			return $this->resolved;
		}

		// read here rather than in the constructor: this is a service of every request, and the
		// mapping of the whole hierarchy is not something a request that never calls MCP should load
		$byClass  = array_flip($this->em->getClassMetadata(Record::class)->discriminatorMap);
		$resolved = [];
		foreach ($this->classes as $class) {
			if (!isset($byClass[$class])) {
				// the map lives in the application's own Record.orm.xml, so a class missing from it is
				// a configuration mistake, and silence would leave the type simply never found - the
				// way LogList drops unknown classes, which is how this bundle's own test application
				// came to list a class that does not exist
				throw new LogicException(sprintf(
					'"%s" is configured under bug_catcher.mcp.record_types but is not in the discriminator '
					. 'map of "%s", which knows: %s. Add it to the map first - see docs/custom_record.md.',
					$class, Record::class, implode(', ', array_keys($byClass)),
				));
			}
			// subclasses come along, the way "INSTANCE OF RecordLog" would also match RecordLogTrace
			foreach ([$class, ...$this->em->getClassMetadata($class)->subClasses] as $covered) {
				if (isset($byClass[$covered])) {
					$resolved[$byClass[$covered]] = $covered;
				}
			}
		}

		return $this->resolved = $resolved;
	}
}
