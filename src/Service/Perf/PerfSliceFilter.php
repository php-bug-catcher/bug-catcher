<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf;

use BugCatcher\Entity\PerfBucket;

/**
 * Which rows of a window a report reads, beyond the project and the time.
 *
 * Every field narrows; an empty filter is the whole window. The dashboard only ever narrows to one
 * route, which is why the repository took a bare path hash for so long - the MCP tools also search
 * for a route by part of its path and pick one machine or vhost out of several.
 *
 * {@see \BugCatcher\Repository\PerfBucketRepository} applies it to the measurements *and* to the
 * extras of the same window, identically: a filter on one of the two queries only would hand a
 * route the database time of routes it does not match.
 */
final readonly class PerfSliceFilter
{
	/**
	 * @param string|null $pathHash one route, as {@see PerfBucket::hashPath()} spells it
	 * @param string|null $pathContains routes whose path contains this, matched literally - a `%`
	 *     or `_` in it is a character, not a wildcard
	 * @param string|null $host one vhost
	 * @param string|null $serverName one machine
	 */
	public function __construct(
		public ?string $pathHash = null,
		public ?string $pathContains = null,
		public ?string $host = null,
		public ?string $serverName = null,
	) {
	}

	public static function path(?string $path): self
	{
		return new self($path === null ? null : PerfBucket::hashPath($path));
	}

	public function withPathHash(?string $pathHash): self
	{
		return new self($pathHash, $this->pathContains, $this->host, $this->serverName);
	}

	public function isEmpty(): bool
	{
		return $this->pathHash === null
			&& ($this->pathContains === null || $this->pathContains === '')
			&& $this->host === null
			&& $this->serverName === null;
	}
}
