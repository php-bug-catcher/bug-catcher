<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Detection;

/** What one run of `app:perf:detect` looked at and what it found. */
final readonly class DetectionResult
{
	public function __construct(
		public int $projects,
		public int $findings,
	) {
	}

	public function summary(): string
	{
		return sprintf(
			'%d project%s, %d regression%s',
			$this->projects,
			$this->projects === 1 ? '' : 's',
			$this->findings,
			$this->findings === 1 ? '' : 's',
		);
	}
}
