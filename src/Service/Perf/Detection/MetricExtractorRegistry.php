<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection;

use InvalidArgumentException;

/**
 * The metrics a detector may be pointed at, by name.
 *
 * A named map wired in `BugCatcherBundle::loadExtension()` - the four built-ins merged with
 * whatever `bug_catcher.perf.metrics` adds - which is the same idiom `PingCollectorCommand` uses
 * for its collectors, and the reason adding a metric needs no compiler pass and no tag.
 */
final readonly class MetricExtractorRegistry
{
	/** @var array<string, MetricExtractorInterface> */
	private array $extractors;

	/** @param array<string, MetricExtractorInterface> $extractors name => extractor */
	public function __construct(array $extractors)
	{
		foreach ($extractors as $name => $extractor) {
			// the key is what the configuration says and the name is what lands in
			// record_performance.metric; two answers to one question would mean records nobody
			// can find again
			if ($extractor->name() !== $name) {
				throw new InvalidArgumentException(sprintf(
					'The metric registered as %s calls itself %s; a metric has one name.',
					$name,
					$extractor->name(),
				));
			}
		}

		$this->extractors = $extractors;
	}

	public function get(string $name): MetricExtractorInterface
	{
		return $this->extractors[$name] ?? throw new InvalidArgumentException(sprintf(
			'There is no performance metric called %s. Known: %s.',
			$name,
			implode(', ', $this->names()) ?: 'none',
		));
	}

	public function has(string $name): bool
	{
		return isset($this->extractors[$name]);
	}

	/** @return list<string> */
	public function names(): array
	{
		return array_keys($this->extractors);
	}
}
