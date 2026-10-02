<?php

declare(strict_types=1);

namespace BugCatcher\Enum;

/**
 * A metric a regression can be detected on, stored on
 * {@see \BugCatcher\Entity\RecordPerformance} and selected by `bug_catcher.perf.anomaly.metric`.
 *
 * The values are the configuration keys, so they are also the names a custom
 * `MetricExtractorInterface` competes with — see docs/custom_perf_metric.md.
 */
enum PerfMetric: string
{
	case P95       = 'p95';
	case Avg       = 'avg';
	case ErrorRate = 'error_rate';
	case Mem       = 'mem';

	public function label(): string
	{
		return match ($this) {
			self::P95       => 'p95 latency',
			self::Avg       => 'Average latency',
			self::ErrorRate => 'Error rate',
			self::Mem       => 'Memory per hit',
		};
	}

	public function unit(): PerfUnit
	{
		return match ($this) {
			self::P95, self::Avg => PerfUnit::Milliseconds,
			self::ErrorRate      => PerfUnit::Ratio,
			self::Mem            => PerfUnit::Bytes,
		};
	}

	public function format(float $value): string
	{
		return $this->unit()->format($value);
	}
}
