<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Retention;

/**
 * What one run of `app:perf:purge` removed, per granularity - or would have removed, when it was
 * asked not to.
 */
final readonly class PurgeResult
{
	/** @param array<string, int> $rows granularity value => rows */
	public function __construct(
		public array $rows,
		public bool $dryRun = false,
	) {
	}

	public function total(): int
	{
		return array_sum($this->rows);
	}

	public function summary(): string
	{
		$parts = [];
		foreach ($this->rows as $granularity => $rows) {
			$parts[] = sprintf('%s %d', $granularity, $rows);
		}

		return sprintf(
			'%s %d rows (%s)',
			$this->dryRun ? 'would delete' : 'deleted',
			$this->total(),
			implode(', ', $parts),
		);
	}
}
