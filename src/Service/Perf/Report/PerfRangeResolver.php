<?php

declare(strict_types=1);

namespace BugCatcher\Service\Perf\Report;

use BugCatcher\Service\Perf\Report\Dto\PerfRange;
use DateInterval;
use DateTimeImmutable;

/**
 * Two query parameters into a window, in one place rather than three.
 *
 * Every panel used to compute its own `new DateTimeImmutable("-{$this->hours} hours")` to `now`,
 * which is both a duplicated decision and a window that can only ever end in the present. This
 * turns the pair `(at, hours)` into {@see PerfRange}: `[at, at + hours)` when there is an anchor,
 * and the last `hours` hours when there is not.
 *
 * It is also the only thing between the query string and a chart, so the two ways a URL can be
 * hostile are answered here and nowhere downstream.
 */
final readonly class PerfRangeResolver
{
	/**
	 * The longest window a panel will read, in hours - one year.
	 *
	 * This is a ceiling on *how much SVG the page is*, not on how much data exists. A chart is
	 * inline in the document, one element per point per series, and the number of points is the
	 * window divided by the bucket width {@see GranularityResolver} picks. Its own docblock names
	 * the three readable sizes: two hours of minutes is 120 points, a week of hours is 168, and a
	 * year of days is 365. A year is therefore the last window that is still a chart; past it the
	 * points keep coming and nobody can read them anyway.
	 *
	 * Without a ceiling `?hours=100000` is a denial of service anyone can type, against a page
	 * that is often behind no firewall at all.
	 */
	public const int MAX_HOURS = 365 * 24;

	/** What a panel shows when nobody has asked for anything - the same hour it always showed. */
	public const int DEFAULT_HOURS = 1;

	public function resolve(?string $at, int $hours): PerfRange
	{
		$length = new DateInterval(sprintf('PT%dH', $this->hours($hours)));
		$anchor = $this->anchor($at);

		if ($anchor === null) {
			$to = new DateTimeImmutable();

			return new PerfRange($to->sub($length), $to, true);
		}

		return new PerfRange($anchor, $anchor->add($length), false);
	}

	/**
	 * The length, held between one hour and {@see MAX_HOURS}.
	 *
	 * Clamped rather than rejected: `hours` arrives from a `<select>`, from a link somebody wrote
	 * by hand, and from an old bookmark, and a 404 for a window that is merely too wide tells
	 * nobody anything. Zero and negatives would reach
	 * {@see GranularityResolver::window()} as a window that ends before it starts, which throws.
	 */
	private function hours(int $hours): int
	{
		return max(1, min(self::MAX_HOURS, $hours));
	}

	/**
	 * The anchor, or null for every string that is not one.
	 *
	 * Strict, and deliberately not `new DateTimeImmutable($at)`: that would accept `now`,
	 * `+3 weeks` and `last monday` out of a query string, so the window a link opened would depend
	 * on when it was clicked. One format, the one {@see PerfRange::AT_FORMAT} writes.
	 *
	 * A value that does not parse falls back to the live window instead of throwing. A hand-edited
	 * URL, a truncated link in an email or an `at` from a format we stop using must not be a 500.
	 */
	private function anchor(?string $at): ?DateTimeImmutable
	{
		if ($at === null || $at === '') {
			return null;
		}

		// the leading `!` zeroes the fields the format does not mention; without it the seconds and
		// microseconds are taken from the current time, so the same `at` names a different instant
		// every second and no two reads of one page agree
		$parsed = DateTimeImmutable::createFromFormat('!'.PerfRange::AT_FORMAT, $at);
		$errors = DateTimeImmutable::getLastErrors();

		// warnings, not just errors: `2026-13-45T00:00` parses, by rolling over into next year
		if ($parsed === false || ($errors !== false && ($errors['error_count'] > 0 || $errors['warning_count'] > 0))) {
			return null;
		}

		return $parsed;
	}
}
