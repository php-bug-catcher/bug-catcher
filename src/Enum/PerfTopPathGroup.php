<?php

declare(strict_types=1);

namespace BugCatcher\Enum;

/**
 * What a row of the top-paths table stands for.
 *
 * Grouping by machine or vhost answers a different question from grouping by route: "is one of
 * the three web servers slow" rather than "which route is slow", and the design keeps
 * `server_name` and `host` apart precisely so that it can be asked.
 */
enum PerfTopPathGroup: string
{
	case Path   = 'path';
	case Host   = 'host';
	case Server = 'server';

	/** The field of {@see \BugCatcher\Entity\PerfBucket} the report groups by. */
	public function field(): string
	{
		return match ($this) {
			self::Path   => 'pathHash',
			self::Host   => 'host',
			self::Server => 'serverName',
		};
	}

	public function label(): string
	{
		return match ($this) {
			self::Path   => 'Route',
			self::Host   => 'Vhost',
			self::Server => 'Machine',
		};
	}
}
