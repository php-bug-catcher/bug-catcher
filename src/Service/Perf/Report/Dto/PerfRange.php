<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Report\Dto;

use DateTimeImmutable;

/**
 * The window a panel was asked for: an anchor and a length, or the last N hours.
 *
 * Not {@see \BugCatcher\Service\Perf\PerfWindow}, which carries the bucket width as well. That
 * decision belongs to {@see \BugCatcher\Service\Perf\Report\GranularityResolver} and is made later,
 * inside {@see \BugCatcher\Service\Perf\Report\PerfReportBuilder}; this is only what the page asked
 * for, before anything has decided how finely to read it.
 *
 * `live` is the difference between "the last hour" and "the hour starting at 14:00" - the same
 * numbers today, and not the same question tomorrow. The panels need it to know whether the pickers
 * should be showing a date at all.
 */
final readonly class PerfRange
{
	/**
	 * What the `at` query parameter carries and the pickers write back.
	 *
	 * Minutes, no seconds and no timezone: buckets are stored on the server's clock, so an offset
	 * in the URL would be a second opinion about what 14:00 means. The consequence is that `at` is
	 * the *server's* wall clock, not the reader's - which is why
	 * `assets/controllers/perf-range_controller.js` turns the time picker's "now" button off. A
	 * button that inserted the browser's clock would select an hour with no traffic in it on any
	 * machine in a different timezone from the server.
	 */
	public const string AT_FORMAT = 'Y-m-d\TH:i';

	public function __construct(
		public DateTimeImmutable $from,
		public DateTimeImmutable $to,
		public bool $live,
	) {
	}

	/** The anchor as `at` spells it, or null while the window follows the clock. */
	public function anchor(): ?string
	{
		return $this->live ? null : $this->from->format(self::AT_FORMAT);
	}

	/**
	 * The day half of the anchor, for the datepicker to bind to.
	 *
	 * Empty rather than today's date while live: a filled-in picker on a window that is following
	 * the clock reads as a window that is pinned to that day, which is the opposite of true.
	 */
	public function date(): string
	{
		return $this->live ? '' : $this->from->format('Y-m-d');
	}

	/** The time half, for the timepicker. Empty while live, for the same reason. */
	public function time(): string
	{
		return $this->live ? '' : $this->from->format('H:i');
	}
}
