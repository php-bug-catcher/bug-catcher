<?php

declare(strict_types=1);

namespace BugCatcher\Tests\Unit\Entity;

use BugCatcher\Entity\Project;
use BugCatcher\Entity\RecordPerformance;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Mcp\HasMcpDetails;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Uid\Uuid;

class RecordPerformanceTest extends TestCase
{
	public function testTheRecordKnowsWhichRouteRegressedAndByHowMuch(): void
	{
		$record = $this->record();

		$this->assertSame('/checkout', $record->getPath());
		$this->assertSame('p95', $record->getMetric());
		$this->assertSame(PerfUnit::Milliseconds, $record->getUnit());
		$this->assertSame(210.0, $record->getBaseline());
		$this->assertSame(3100.0, $record->getObserved());
		$this->assertSame('2026-03-10 14:35:00', $record->getWindowAt()->format('Y-m-d H:i:s'));
	}

	/** It exists because a threshold was crossed; there is no non-error performance record. */
	public function testItIsAlwaysAnError(): void
	{
		$this->assertTrue($this->record()->isError());
	}

	/**
	 * The same route regressing again is the same entry, the way two reports of one exception are.
	 * The window is deliberately not in the hash: including it would give every five-minute run a
	 * row of its own and the dashboard would be nothing else.
	 */
	public function testTheSameRouteAndMetricIsTheSameEntry(): void
	{
		$project = $this->project();

		$this->assertSame(
			$this->record($project)->calculateHash(),
			$this->record($project, observed: 9000.0, windowAt: '2026-03-11 09:00:00')->calculateHash(),
		);
	}

	public function testAnotherRouteOrAnotherMetricIsAnotherEntry(): void
	{
		$project = $this->project();
		$hash    = $this->record($project)->calculateHash();

		$this->assertNotSame($hash, $this->record($project, path: '/cart')->calculateHash());
		$this->assertNotSame($hash, $this->record($project, metric: 'avg')->calculateHash());
		$this->assertNotSame($hash, $this->record($this->project())->calculateHash());
	}

	public function testTheMessageSaysWhatHappenedInTheUnitOfTheMetric(): void
	{
		$this->assertSame(
			'p95 latency on /checkout rose from 210 ms to 3.1 s',
			$this->record()->getMessage(),
		);
		$this->assertSame(
			'Error rate on /checkout rose from 0.1% to 12%',
			$this->record(metric: 'error_rate', unit: PerfUnit::Ratio, baseline: 0.001, observed: 0.12)->getMessage(),
		);
	}

	/**
	 * A metric registered under `perf.metrics` is not one of the four built-ins, and the record
	 * still has to render a year later - which is also why the unit is stored rather than looked
	 * up in a registry that may no longer know the name.
	 */
	public function testAMetricTheBundleDoesNotKnowStillReadsAsASentence(): void
	{
		$record = $this->record(metric: 'db_time', unit: PerfUnit::Milliseconds, baseline: 20.0, observed: 400.0);

		$this->assertSame('db_time on /checkout rose from 20 ms to 400 ms', $record->getMessage());
	}

	public function testTheRouteIsWhatTheRecordPointsAt(): void
	{
		$this->assertSame('/checkout', $this->record()->getRequestUri());
	}

	public function testItRendersWithTheOrdinaryRecordRow(): void
	{
		$this->assertSame('LogList:RecordLog', $this->record()->getComponentName());
	}

	public function testAnAssistantGetsTheNumbersAndNotOnlyTheSentence(): void
	{
		$record = $this->record();

		$this->assertInstanceOf(HasMcpDetails::class, $record);
		$this->assertSame([
			'path'     => '/checkout',
			'metric'   => 'p95',
			'unit'     => 'ms',
			'baseline' => 210.0,
			'observed' => 3100.0,
			'windowAt' => '2026-03-10 14:35:00',
		], $record->getMcpDetails());
	}

	/**
	 * Without this the getter is serialised as a `mcpDetails` property of every API response that
	 * ever carries the record.
	 */
	public function testTheMcpDetailsAreNotPartOfTheApiRepresentation(): void
	{
		$attributes = (new ReflectionMethod(RecordPerformance::class, 'getMcpDetails'))
			->getAttributes(Ignore::class);

		$this->assertCount(1, $attributes);
	}

	private function record(
		?Project $project = null,
		string $path = '/checkout',
		string $metric = 'p95',
		PerfUnit $unit = PerfUnit::Milliseconds,
		float $baseline = 210.0,
		float $observed = 3100.0,
		string $windowAt = '2026-03-10 14:35:00',
	): RecordPerformance {
		return new RecordPerformance(
			$project ?? $this->project(),
			$path,
			$metric,
			$unit,
			$baseline,
			$observed,
			new DateTimeImmutable($windowAt),
		);
	}

	/** The hash leans on the project's identifier, which a factory-free unit test has to fake. */
	private function project(): Project
	{
		$project = new Project();
		$id      = new \ReflectionProperty(Project::class, 'id');
		$id->setValue($project, Uuid::v7());

		return $project;
	}
}
