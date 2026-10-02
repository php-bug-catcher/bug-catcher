<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Report\Dto;

use BugCatcher\Enum\PerfTopPathGroup;
use BugCatcher\Enum\PerfTopPathSort;
use BugCatcher\Service\Perf\PerfWindow;

/**
 * The heaviest rows of a window, in the order they were asked for.
 *
 * `truncated` is not decoration: beyond a limit the query stops grouping, and a table that
 * silently shows the top of a list it cut off reads as the whole list.
 */
final readonly class TopPathsReport
{
	/** @param list<TopPathRow> $rows */
	public function __construct(
		public PerfWindow $window,
		public PerfTopPathGroup $group,
		public PerfTopPathSort $sort,
		public array $rows,
		public bool $truncated = false,
	) {
	}

	public function isEmpty(): bool
	{
		return $this->rows === [];
	}
}
