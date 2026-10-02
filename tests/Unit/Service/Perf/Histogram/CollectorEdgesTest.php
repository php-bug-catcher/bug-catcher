<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Histogram;

use BugCatcher\Service\Perf\Histogram\HistogramBins;
use PHPUnit\Framework\TestCase;

/**
 * The two sides of the wire, compared against each other.
 *
 * `HistogramBins::EDGES_MS` is duplicated from
 * `BugCatcher\PerfCollector\Histogram\DurationHistogram::EDGES_MS` in `php-bug-catcher/perf-collector`,
 * which is not a dependency of this package and never will be: the collector installs into
 * applications that have never heard of this bundle. {@see HistogramBinsTest} pins the server's
 * edges against a literal; this one pins them against the collector itself, whenever the checkout
 * happens to sit next to this one.
 *
 * It skips itself when it does not, so a machine with only this repository - CI among them - is
 * not broken by the absence. A skip here is a reminder, not a failure; the literal in
 * `HistogramBinsTest` is the guard that always runs.
 */
class CollectorEdgesTest extends TestCase
{
	/** The sibling checkout: /contrib/bug-catcher-perf-collector next to /contrib/bug-catcher-bundle. */
	private const COLLECTOR_FILE = __DIR__ . '/../../../../../../bug-catcher-perf-collector/src/Histogram/DurationHistogram.php';

	private const COLLECTOR_CLASS = 'BugCatcher\PerfCollector\Histogram\DurationHistogram';

	/**
	 * The one file is self-contained - no `require`, no autoloader, no dependency of its own - so
	 * including it is enough to read its constants and to exercise `record()`.
	 */
	public static function setUpBeforeClass(): void
	{
		if (class_exists(self::COLLECTOR_CLASS)) {
			return;
		}

		$file = realpath(self::COLLECTOR_FILE);

		if ($file === false) {
			self::markTestSkipped(sprintf(
				'The perf-collector checkout is not at %s; nothing to compare against.',
				self::COLLECTOR_FILE,
			));
		}

		require_once $file;

		if (!class_exists(self::COLLECTOR_CLASS)) {
			self::markTestSkipped(sprintf('%s does not declare %s.', $file, self::COLLECTOR_CLASS));
		}
	}

	public function testBothPackagesCountOnTheSameEdges(): void
	{
		$this->assertSame(
			constant(self::COLLECTOR_CLASS . '::EDGES_MS'),
			HistogramBins::EDGES_MS,
			'The collector and the server disagree about the histogram scale; stored histograms would stop meaning one thing.',
		);
	}

	public function testBothPackagesCountIntoTheSameNumberOfBins(): void
	{
		$this->assertSame(
			constant(self::COLLECTOR_CLASS . '::BIN_COUNT'),
			HistogramBins::COUNT,
			'A histogram shipped by the collector would not fit the columns the server stores it in.',
		);
	}

	/**
	 * Equal edges are not enough: the two sides also have to agree on which side of an edge a
	 * duration falls. Both call the bin lower-inclusive, and this is where that is checked rather
	 * than asserted in two docblocks.
	 *
	 * @dataProvider durations
	 */
	public function testADurationLandsInTheSameBinOnBothSides(float $milliseconds): void
	{
		$class     = self::COLLECTOR_CLASS;
		$histogram = new $class();
		$histogram->record($milliseconds / 1000);

		$shipped = $histogram->toArray();
		$bin     = HistogramBins::binFor($milliseconds);

		$expected = array_fill(0, HistogramBins::COUNT, 0);
		$expected[$bin] = 1;

		$this->assertSame($expected, $shipped, sprintf('%s ms is not bin %d to the collector.', $milliseconds, $bin));
	}

	/**
	 * Every edge, just below it and just above it, plus the ends of the scale.
	 *
	 * @return iterable<string, array{float}>
	 */
	public function durations(): iterable
	{
		yield 'zero' => [0.0];

		foreach (HistogramBins::EDGES_MS as $edge) {
			yield $edge . ' ms, the edge itself' => [(float)$edge];
			yield 'just under ' . $edge . ' ms' => [$edge - 0.001];
			yield 'just over ' . $edge . ' ms'  => [$edge + 0.001];
		}

		yield 'an hour, well into the overflow bin' => [3_600_000.0];
	}
}
