<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Integration\Perf;

use BugCatcher\Entity\PerfBucket;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfGranularity;
use BugCatcher\Repository\PerfBucketRepository;
use BugCatcher\Service\Perf\Histogram\HistogramBins;
use BugCatcher\Service\Perf\Histogram\PercentileEstimator;
use BugCatcher\Service\Perf\Ingest\PerfBucketUpserter;
use BugCatcher\Service\Perf\Report\Dto\PerfHealth;
use BugCatcher\Service\Perf\Report\GranularityResolver;
use BugCatcher\Service\Perf\Report\PerfReportBuilder;
use BugCatcher\Tests\App\Factory\ProjectFactory;
use BugCatcher\Tests\App\KernelTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\Factories;

/**
 * The window reduced to the numbers a dashboard row has space for.
 */
class PerfHealthTest extends KernelTestCase
{
	use Factories;

	private Project $project;

	protected function setUp(): void
	{
		parent::setUp();
		$this->project = ProjectFactory::createOne()->_real();
	}

	/**
	 * Apdex counts a request whole up to 500 ms, half up to 2 s and not at all beyond, and both
	 * of those are bin edges - so this is arithmetic, not an estimate.
	 */
	public function testApdexIsWholeBinsAndNothingInterpolated(): void
	{
		// 6 fast, 2 tolerable, 2 frustrating => (6 + 2/2) / 10
		$this->minutes([50.0 => 6, 900.0 => 2, 5000.0 => 2]);

		$this->assertSame(0.7, $this->health()->apdex);
	}

	public function testAWindowOfOnlyFastRequestsScoresOne(): void
	{
		$this->minutes([50.0 => 10]);

		$health = $this->health();
		$this->assertSame(1.0, $health->apdex);
		$this->assertSame('excellent', $health->apdexRating());
	}

	public function testAWindowOfOnlySlowRequestsScoresNothing(): void
	{
		$this->minutes([9000.0 => 10]);

		$health = $this->health();
		$this->assertSame(0.0, $health->apdex);
		$this->assertSame('unacceptable', $health->apdexRating());
	}

	/**
	 * The reason this is a read of its own rather than a sum of the time series: a percentile of
	 * percentiles is not a percentile. Ninety fast requests in one minute and ten slow ones in
	 * the next have a window p95 in the slow band, while the mean of the two per-minute p95s
	 * would land between them.
	 */
	public function testThePercentileIsTakenOffTheWholeWindowNotPerBucket(): void
	{
		$this->minute('14:00', [50.0 => 90]);
		$this->minute('14:01', [5000.0 => 10]);

		// 95th of 100 requests falls in the slow bucket, not between the two per-minute readings
		$this->assertGreaterThan(2000.0, $this->health()->p95Ms);
	}

	public function testAWindowNothingArrivedInIsNullsRatherThanZeroes(): void
	{
		$health = $this->health();

		$this->assertTrue($health->isEmpty());
		$this->assertNull($health->p95Ms);
		$this->assertNull($health->apdex);
		$this->assertNull($health->apdexRating());
		$this->assertNull($health->errorRate);
		$this->assertNull($health->requestsPerMinute());
	}

	public function testThroughputIsPerMinuteSoTwoWindowsAreComparable(): void
	{
		$this->minutes([50.0 => 120]);

		// 120 requests over the five-minute window the helper writes into
		$this->assertEqualsWithDelta(24.0, $this->health()->requestsPerMinute(), 0.001);
	}

	public function testTheErrorRateIsTheShareOfFailedResponses(): void
	{
		$this->minute('14:00', [50.0 => 10], errors: 3);

		$this->assertEqualsWithDelta(0.3, $this->health()->errorRate, 0.001);
	}

	/** Apdex's own bands, so that the colour on the row means what the index says it means. */
	public function testTheRatingFollowsApdexsOwnBands(): void
	{
		$this->assertSame('excellent', $this->rated(0.94));
		$this->assertSame('good', $this->rated(0.93));
		$this->assertSame('fair', $this->rated(0.84));
		$this->assertSame('poor', $this->rated(0.69));
		$this->assertSame('unacceptable', $this->rated(0.49));
	}

	private function rated(float $apdex): string
	{
		return (new PerfHealth(
			(new GranularityResolver())->window(
				new DateTimeImmutable('2026-03-10 14:00:00'),
				new DateTimeImmutable('2026-03-10 14:05:00'),
			),
			1,
			1.0,
			$apdex,
			0.0,
		))->apdexRating();
	}

	private function health(): PerfHealth
	{
		return $this->builder()->health(
			$this->project,
			new DateTimeImmutable('2026-03-10 14:00:00'),
			new DateTimeImmutable('2026-03-10 14:05:00'),
		);
	}

	private function builder(): PerfReportBuilder
	{
		return new PerfReportBuilder(
			self::getContainer()->get(PerfBucketRepository::class),
			new PercentileEstimator(),
			new GranularityResolver(),
		);
	}

	/** @param array<int|string, int> $msToHits */
	private function minutes(array $msToHits): void
	{
		$this->minute('14:00', $msToHits);
	}

	/** @param array<int|string, int> $msToHits how many requests landed at each duration */
	private function minute(string $time, array $msToHits, int $errors = 0): void
	{
		$histogram = HistogramBins::empty();
		$hits      = 0;
		$sum       = 0.0;

		foreach ($msToHits as $ms => $count) {
			$histogram[HistogramBins::binFor((float)$ms)] += $count;
			$hits += $count;
			$sum  += (float)$ms / 1000 * $count;
		}

		(new PerfBucketUpserter(self::getContainer()->get(EntityManagerInterface::class)))->upsert([
			new PerfBucket(
				PerfGranularity::Minute,
				new DateTimeImmutable("2026-03-10 {$time}:00"),
				$this->project,
				'web-01',
				'www.site.com',
				'/checkout',
				hits: $hits,
				sumDuration: $sum,
				maxDuration: 1.0,
				clientErrors: $errors,
				durationHistogram: $histogram,
			),
		]);
	}
}
