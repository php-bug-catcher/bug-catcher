<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Report;

use BugCatcher\Service\Perf\Report\Dto\PerfRange;
use BugCatcher\Service\Perf\Report\PerfRangeResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The only thing between `/performance`'s query string and a chart.
 *
 * Half of these pin behaviour and half pin refusals: `at` and `hours` arrive from a URL anybody can
 * edit, on a page an installation is free to leave unauthenticated.
 */
final class PerfRangeResolverTest extends TestCase
{
	private PerfRangeResolver $resolver;

	protected function setUp(): void
	{
		$this->resolver = new PerfRangeResolver();
	}

	/** No anchor is what every panel did before there was one: the last N hours, ending now. */
	public function testWithoutAnAnchorTheWindowFollowsTheClock(): void
	{
		$before = new DateTimeImmutable();
		$range  = $this->resolver->resolve(null, 6);
		$after  = new DateTimeImmutable();

		$this->assertTrue($range->live);
		$this->assertGreaterThanOrEqual($before, $range->to);
		$this->assertLessThanOrEqual($after, $range->to);
		$this->assertSame(6 * 3600, $range->to->getTimestamp() - $range->from->getTimestamp());
	}

	/** An empty `at` is a `?at=` somebody left in the URL, not an anchor at the epoch. */
	public function testAnEmptyAnchorIsTheSameAsNoAnchor(): void
	{
		$this->assertTrue($this->resolver->resolve('', 1)->live);
	}

	/**
	 * `at` is the *start* of the window, not its middle.
	 *
	 * The centring a regression's link wants is done by the link - see
	 * {@see \BugCatcher\Twig\Components\Detail\PerfChart::getAt()} - so that a time somebody picked
	 * by hand is the time they get.
	 */
	public function testAnAnchorStartsTheWindow(): void
	{
		$range = $this->resolver->resolve('2026-10-05T14:30', 1);

		$this->assertFalse($range->live);
		$this->assertSame('2026-10-05 14:30:00', $range->from->format('Y-m-d H:i:s'));
		$this->assertSame('2026-10-05 15:30:00', $range->to->format('Y-m-d H:i:s'));
	}

	/**
	 * The seconds are zero, not "whatever o'clock it is".
	 *
	 * `createFromFormat` without a leading `!` fills the fields the format omits from the current
	 * time, so the same `at` would name a different instant every second and two reads of one page
	 * would disagree.
	 */
	public function testTheAnchorIsNotSecondsPastTheMinuteItNames(): void
	{
		$range = $this->resolver->resolve('2026-10-05T14:30', 1);

		$this->assertSame('00', $range->from->format('s'));
		$this->assertSame(0, (int)$range->from->format('u'));
	}

	/**
	 * A window nobody can read is still a window the server has to draw.
	 *
	 * Every point is an element in the document, so `?hours=100000` is a denial of service anyone
	 * can type. Clamped rather than refused: a too-wide window is a mistake, not an attack worth a
	 * 404.
	 */
	public function testAnAbsurdLengthIsHeldAtTheCeiling(): void
	{
		$range = $this->resolver->resolve('2026-10-05T00:00', 100_000);

		$this->assertSame(
			PerfRangeResolver::MAX_HOURS * 3600,
			$range->to->getTimestamp() - $range->from->getTimestamp(),
		);
	}

	/**
	 * Zero and negatives would reach GranularityResolver::window() as a window that ends before it
	 * starts, which throws - so a 500 for `?hours=0`.
	 *
	 * @dataProvider notALength
	 */
	public function testALengthOfNothingBecomesAnHour(int $hours): void
	{
		$range = $this->resolver->resolve('2026-10-05T14:30', $hours);

		$this->assertSame(3600, $range->to->getTimestamp() - $range->from->getTimestamp());
		$this->assertLessThan($range->to, $range->from);
	}

	public static function notALength(): iterable
	{
		yield 'zero' => [0];
		yield 'negative' => [-5];
		yield 'very negative' => [PHP_INT_MIN];
	}

	/**
	 * A hand-edited URL, a link truncated by a mail client, or an `at` in a format we stopped
	 * using: all of them fall back to the live window rather than throwing.
	 *
	 * @dataProvider notAnAnchor
	 */
	public function testAnAnchorThatIsNotOneFallsBackToLive(string $at): void
	{
		$this->assertTrue($this->resolver->resolve($at, 1)->live, $at.' should not have parsed');
	}

	public static function notAnAnchor(): iterable
	{
		yield 'prose' => ['not-a-date'];
		// the reason warning_count is checked and not just error_count: this one "parses", by
		// rolling the 13th month into next year and the 45th day into next month
		yield 'rolled over' => ['2026-13-45T00:00'];
		yield 'hour 99' => ['2026-10-05T99:00'];
		yield 'a date with no time' => ['2026-10-05'];
		yield 'seconds we do not write' => ['2026-10-05T14:30:15'];
		yield 'an offset we do not write' => ['2026-10-05T14:30+02:00'];
		yield 'a relative expression' => ['+3 weeks'];
		yield 'now' => ['now'];
	}

	/**
	 * `now` and `+3 weeks` deserve their own note: `new DateTimeImmutable($at)` would accept both,
	 * and a link's window would then depend on when it was clicked rather than on what it says.
	 */
	public function testARelativeExpressionIsNotAnAnchor(): void
	{
		$range = $this->resolver->resolve('+3 weeks', 1);

		$this->assertTrue($range->live);
		$this->assertLessThanOrEqual(new DateTimeImmutable(), $range->to);
	}

	/** What the pickers bind to, and what the link writes, are the same two halves of one value. */
	public function testTheRangeSpellsItsAnchorBackTheWayItWasGiven(): void
	{
		$range = $this->resolver->resolve('2026-10-05T14:30', 1);

		$this->assertSame('2026-10-05T14:30', $range->anchor());
		$this->assertSame('2026-10-05', $range->date());
		$this->assertSame('14:30', $range->time());
	}

	/**
	 * A live window leaves the pickers empty on purpose: a date in them would read as a window
	 * pinned to that day, which is the opposite of what a live window is.
	 */
	public function testALiveRangeHasNothingForThePickersToShow(): void
	{
		$range = $this->resolver->resolve(null, 1);

		$this->assertNull($range->anchor());
		$this->assertSame('', $range->date());
		$this->assertSame('', $range->time());
	}

	/** The ceiling is a year, which GranularityResolver reads as days - 365 points, still a chart. */
	public function testTheCeilingIsAYear(): void
	{
		$this->assertSame(365 * 24, PerfRangeResolver::MAX_HOURS);
		$this->assertSame('Y-m-d\TH:i', PerfRange::AT_FORMAT);
	}
}
