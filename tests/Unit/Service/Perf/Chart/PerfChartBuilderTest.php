<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Service\Perf\Chart;

use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Service\Perf\Chart\AtelierThemeFactory;
use BugCatcher\Service\Perf\Chart\CpuBreakdownChartBuilder;
use BugCatcher\Service\Perf\Chart\DatabaseChartBuilder;
use BugCatcher\Service\Perf\Chart\DetailChartBuilder;
use BugCatcher\Service\Perf\Chart\LatencyBandsChartBuilder;
use BugCatcher\Service\Perf\Chart\StatusMixChartBuilder;
use BugCatcher\Service\Perf\Chart\ThroughputLatencyChartBuilder;
use BugCatcher\Service\Perf\PerfWindow;
use BugCatcher\Service\Perf\Report\Dto\LatencyBands;
use BugCatcher\Service\Perf\Report\Dto\PathDetailReport;
use BugCatcher\Service\Perf\Report\Dto\PerfTimePoint;
use BugCatcher\Service\Perf\Report\Dto\PerfTimeSeries;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

class PerfChartBuilderTest extends TestCase
{
	private AtelierThemeFactory $themes;

	/**
	 * A translator with no catalogue hands every message back unchanged, which is what keeps the
	 * assertions below about the chart rather than about the Slovak for "p95".
	 */
	private TranslatorInterface $translator;

	protected function setUp(): void
	{
		$this->themes     = new AtelierThemeFactory();
		$this->translator = new Translator('en');
	}

	/**
	 * Inlined into the page, sized by the panel, and themed by the stylesheet. A fixed width or a
	 * stray XML declaration would break all three.
	 *
	 * @dataProvider everyChart
	 */
	public function testEveryChartIsMarkupAPageCanInline(callable $build): void
	{
		$svg = $build($this->series(12));

		$this->assertStringStartsWith('<svg', $svg);
		$this->assertStringNotContainsString('<?xml', $svg);
		$this->assertStringContainsString('viewBox="0 0 960', $svg);
		$this->assertStringContainsString('class="atelier-chart"', $svg);

		// the root element carries no size of its own, so the panel decides how wide it is
		preg_match('/^<svg[^>]*>/', $svg, $root);
		$this->assertStringNotContainsString(' width=', $root[0]);
		$this->assertStringNotContainsString(' height=', $root[0]);
	}

	/**
	 * The one thing that would quietly undo the design system: a colour baked in at render time
	 * cannot follow a theme the browser switches afterwards.
	 *
	 * @dataProvider everyChart
	 */
	public function testNoChartPaintsAColourOfItsOwn(callable $build): void
	{
		$svg = $build($this->series(12));

		$this->assertStringContainsString('var(--bc-', $svg);
		$this->assertDoesNotMatchRegularExpression('/(fill|stroke)="#[0-9a-fA-F]{3,8}"/', $svg);
	}

	/** @dataProvider everyChart */
	public function testAWindowWithNoBucketsDrawsNothingAtAll(callable $build): void
	{
		$this->assertSame('', $build($this->series(0)));
	}

	/** @dataProvider everyChart */
	public function testOneBucketIsStillAChart(callable $build): void
	{
		$this->assertStringStartsWith('<svg', $build($this->series(1)));
	}

	/** @return array<string, array{callable}> */
	public function everyChart(): array
	{
		$themes     = new AtelierThemeFactory();
		$translator = new Translator('en');

		return [
			'throughput' => [static fn(PerfTimeSeries $s): string
				=> (new ThroughputLatencyChartBuilder($themes, $translator))->build($s)['hits']],
			'latency'    => [static fn(PerfTimeSeries $s): string
				=> (new ThroughputLatencyChartBuilder($themes, $translator))->build($s)['latency']],
			'bands'      => [static fn(PerfTimeSeries $s): string
				=> (new LatencyBandsChartBuilder($themes, $translator))->build($s)],
			'cpu'        => [static fn(PerfTimeSeries $s): string
				=> (new CpuBreakdownChartBuilder($themes, $translator))->build($s)],
			'status'     => [static fn(PerfTimeSeries $s): string
				=> (new StatusMixChartBuilder($themes, $translator))->build($s)],
			'queries'    => [static fn(PerfTimeSeries $s): string
				=> (new DatabaseChartBuilder($themes, $translator))->build($s)['queries']],
			'db time'    => [static fn(PerfTimeSeries $s): string
				=> (new DatabaseChartBuilder($themes, $translator))->build($s)['time']],
		];
	}

	/**
	 * Two charts rather than one with two axes, and they have to line up: the plot box is laid
	 * out from fixed insets, so equal widths mean equal plots.
	 */
	public function testThroughputAndLatencyAreTwoChartsOfOneWidth(): void
	{
		$charts = (new ThroughputLatencyChartBuilder($this->themes, $this->translator))->build($this->series(12));

		$this->assertStringContainsString('viewBox="0 0 960 200"', $charts['hits']);
		$this->assertStringContainsString('viewBox="0 0 960 280"', $charts['latency']);
		$this->assertStringContainsString('mean', $charts['latency']);
		$this->assertStringContainsString('p95', $charts['latency']);
	}

	/**
	 * The database line shares a chart with the waiting band it is a part of, so the two have to
	 * be told apart by colour - one hue for both would read as a single line.
	 */
	public function testTheDatabaseChartDrawsTheQueriesAndTheTimeTheyCost(): void
	{
		$at     = new DateTimeImmutable('2026-03-10 14:00:00');
		$series = new PerfTimeSeries($this->window(2), [
			$this->point($at, avgMs: 100.0, userMs: 40.0, sysMs: 10.0, extra: ['sq' => 120.0, 'st' => 0.4]),
			$this->point($at->add(new DateInterval('PT1M')), avgMs: 200.0, userMs: 40.0, sysMs: 10.0,
				extra: ['sq' => 240.0, 'st' => 0.9]),
		]);

		$charts = (new DatabaseChartBuilder($this->themes, $this->translator))->build($series);

		$this->assertStringContainsString('var(--bc-perf-queries)', $charts['queries']);
		$this->assertStringContainsString('var(--bc-perf-dbtime)', $charts['time']);
		$this->assertStringContainsString('var(--bc-perf-wait)', $charts['time']);
		$this->assertStringNotContainsString('var(--bc-perf-dbtime)', $charts['queries']);
	}

	/** A window nobody instrumented still draws: the panel, not the builder, decides to say so. */
	public function testTheDatabaseChartSurvivesBucketsThatCountedNothing(): void
	{
		$charts = (new DatabaseChartBuilder($this->themes, $this->translator))->build($this->series(12));

		$this->assertStringStartsWith('<svg', $charts['queries']);
		$this->assertStringStartsWith('<svg', $charts['time']);
	}

	public function testTheFiveLatencyBandsAreAllThere(): void
	{
		$svg = (new LatencyBandsChartBuilder($this->themes, $this->translator))->build($this->series(12));

		// the labels are XML-escaped in the markup ("&lt; 100 ms"), so they are compared escaped
		foreach (array_keys(LatencyBands::empty()->toArray()) as $band) {
			$this->assertStringContainsString(htmlspecialchars($band, ENT_XML1), $svg);
		}

		for ($band = 1; $band <= 5; $band++) {
			$this->assertStringContainsString("var(--bc-perf-band-{$band})", $svg);
		}
	}

	/** Negative values are refused by the library, and wallclock minus CPU can round below zero. */
	public function testTheWaitingBandSurvivesCpuThatOutranWallclock(): void
	{
		$series = new PerfTimeSeries(
			$this->window(2),
			[
				$this->point(new DateTimeImmutable('2026-03-10 14:00:00'), avgMs: 100.0, userMs: 90.0, sysMs: 30.0),
				$this->point(new DateTimeImmutable('2026-03-10 14:01:00'), avgMs: 100.0, userMs: 10.0, sysMs: 5.0),
			],
		);

		$this->assertStringStartsWith('<svg', (new CpuBreakdownChartBuilder($this->themes, $this->translator))->build($series));
	}

	/**
	 * Every category label is painted, with no thinning in the library, so a two-hour window of
	 * minutes would be 120 labels overprinting into a smear.
	 */
	public function testALongWindowDoesNotPrintEveryLabel(): void
	{
		$svg = (new LatencyBandsChartBuilder($this->themes, $this->translator))->build($this->series(120));

		$this->assertLessThanOrEqual(
			12,
			preg_match_all('/class="atelier-chart__category-label"/', $svg),
		);
	}

	public function testAShortWindowKeepsAllItsLabels(): void
	{
		$svg = (new LatencyBandsChartBuilder($this->themes, $this->translator))->build($this->series(8));

		$this->assertSame(8, preg_match_all('/class="atelier-chart__category-label"/', $svg));
	}

	/** It renders its own document to append the reference line, so it is the one that can miss it. */
	public function testTheDetailChartThinsItsAxisToo(): void
	{
		$report = new PathDetailReport(
			$this->series(120),
			'/checkout',
			'p95',
			PerfUnit::Milliseconds,
			210.0,
			3100.0,
			new DateTimeImmutable('2026-03-10 14:05:00'),
		);

		$svg = (new DetailChartBuilder($this->themes, $this->translator))->build($report);

		$this->assertLessThanOrEqual(12, preg_match_all('/class="atelier-chart__category-label"/', $svg));
	}

	public function testTheDetailChartDrawsWhatNormalWasAcrossTheSpike(): void
	{
		$svg = (new DetailChartBuilder($this->themes, $this->translator))->build($this->detail('p95', 210.0));

		$this->assertStringContainsString('class="bc-perf-baseline"', $svg);
		$this->assertStringContainsString('stroke-dasharray="6 4"', $svg);
		$this->assertStringContainsString('var(--bc-perf-baseline)', $svg);
	}

	public function testTheDetailChartWithoutABaselineIsStillAChart(): void
	{
		$svg = (new DetailChartBuilder($this->themes, $this->translator))->build($this->detail('avg', null));

		$this->assertStringStartsWith('<svg', $svg);
		$this->assertStringNotContainsString('bc-perf-baseline', $svg);
	}

	/**
	 * A metric an application registered cannot be rebuilt out of the stored buckets, and drawing
	 * p95 under its name would be a chart that says something untrue.
	 */
	public function testAMetricTheBundleCannotRebuildDrawsNoChart(): void
	{
		$this->assertSame('', (new DetailChartBuilder($this->themes, $this->translator))->build($this->detail('db_time', 20.0)));
	}

	/** @dataProvider builtInMetrics */
	public function testTheDetailChartDrawsWhicheverMetricRegressed(string $metric, PerfUnit $unit): void
	{
		$svg = (new DetailChartBuilder($this->themes, $this->translator))->build($this->detail($metric, 1.0, $unit));

		$this->assertStringStartsWith('<svg', $svg);
	}

	/** @return array<string, array{string, PerfUnit}> */
	public static function builtInMetrics(): array
	{
		return [
			'p95'        => ['p95', PerfUnit::Milliseconds],
			'avg'        => ['avg', PerfUnit::Milliseconds],
			'error rate' => ['error_rate', PerfUnit::Ratio],
			'memory'     => ['mem', PerfUnit::Bytes],
		];
	}

	private function detail(string $metric, ?float $baseline, PerfUnit $unit = PerfUnit::Milliseconds): PathDetailReport
	{
		return new PathDetailReport(
			$this->series(12),
			'/checkout',
			$metric,
			$unit,
			$baseline,
			3100.0,
			new DateTimeImmutable('2026-03-10 14:05:00'),
		);
	}

	private function series(int $points): PerfTimeSeries
	{
		$at     = new DateTimeImmutable('2026-03-10 14:00:00');
		$window = $this->window(max(1, $points));
		$made   = [];

		for ($point = 0; $point < $points; $point++) {
			$made[] = $this->point(
				$at->add(new DateInterval("PT{$point}M")),
				avgMs: 100.0 + $point,
				userMs: 40.0,
				sysMs: 10.0,
			);
		}

		return new PerfTimeSeries($window, $made);
	}

	private function window(int $minutes): PerfWindow
	{
		$from = new DateTimeImmutable('2026-03-10 14:00:00');

		return new PerfWindow($from, $from->add(new DateInterval("PT{$minutes}M")), PerfGranularity::Minute);
	}

	/** @param array<string, float> $extra */
	private function point(
		DateTimeImmutable $at,
		float $avgMs,
		float $userMs,
		float $sysMs,
		array $extra = [],
	): PerfTimePoint {
		return new PerfTimePoint(
			$at,
			hits: 10,
			avgMs: $avgMs,
			p95Ms: $avgMs * 2,
			userMs: $userMs,
			sysMs: $sysMs,
			waitMs: max(0.0, $avgMs - $userMs - $sysMs),
			memPerHit: 1_048_576.0,
			maxMem: 2_097_152,
			ok: 8,
			clientErrors: 1,
			serverErrors: 1,
			bands: new LatencyBands(4, 3, 2, 1, 0),
			extra: $extra,
		);
	}
}
