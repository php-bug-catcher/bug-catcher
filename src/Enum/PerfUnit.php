<?php

declare(strict_types=1);

namespace BugCatcher\Enum;

/**
 * What a performance number means, which decides both how it is rendered and how a threshold
 * reads it: `min_absolute_ms` is only meaningful against {@see self::Milliseconds}.
 */
enum PerfUnit: string
{
	case Milliseconds = 'ms';
	case Ratio        = 'ratio';
	case Bytes        = 'bytes';

	private const BYTE_UNITS = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];

	/** The value as a reader of the dashboard or of a notification would say it. */
	public function format(float $value): string
	{
		return match ($this) {
			self::Milliseconds => $this->formatDuration($value),
			self::Ratio        => $this->trim($value * 100, 2) . '%',
			self::Bytes        => $this->formatBytes($value),
		};
	}

	private function formatDuration(float $value): string
	{
		if (abs($value) >= 1000) {
			return $this->trim($value / 1000, 1) . ' s';
		}

		return $this->trim($value, abs($value) < 10 ? 2 : 0) . ' ms';
	}

	private function formatBytes(float $value): string
	{
		$step = 0;
		while (abs($value) >= 1024 && $step < count(self::BYTE_UNITS) - 1) {
			$value /= 1024;
			$step++;
		}

		return $this->trim($value, $step === 0 ? 0 : 1) . ' ' . self::BYTE_UNITS[$step];
	}

	/** Rounds to at most $decimals places and drops a trailing `.0`, so 2.0 reads as "2". */
	private function trim(float $value, int $decimals): string
	{
		$formatted = number_format(round($value, $decimals), $decimals, '.', '');

		if ($decimals > 0) {
			$formatted = rtrim(rtrim($formatted, '0'), '.');
		}

		return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
	}
}
