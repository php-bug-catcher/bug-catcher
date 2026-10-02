<?php

declare(strict_types=1);

namespace BugCatcher\Entity;

use BugCatcher\Enum\PerfMetric;
use BugCatcher\Enum\PerfUnit;
use BugCatcher\Mcp\HasMcpDetails;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * One route whose performance got worse: "p95 latency on /checkout rose from 210 ms to 3.1 s".
 *
 * A regression is an event, so it is a {@see Record} and gets the notifier pipeline, the status
 * workflow, the detail page and MCP without a line of code of its own. The measurements it was
 * found in stay in {@see PerfBucket} - there are three orders of magnitude more of those, and a
 * request that took 40 ms is not something anybody resolves.
 *
 * `metric` is a **string**, not {@see PerfMetric}. An application can register a metric of its own
 * under `bug_catcher.perf.metrics` and select it with `anomaly.metric`, and a regression found on
 * it has to be recordable too. `unit` is stored for the same reason: the record has to render as a
 * sentence a year later, when whatever extractor produced it may no longer be configured.
 *
 * Not `final`: everything in the `Record` hierarchy has to stay open to an application that
 * extends it.
 */
class RecordPerformance extends Record implements HasMcpDetails
{
	protected ?string $path = null;

	protected ?string $metric = null;

	protected ?PerfUnit $unit = null;

	protected ?float $baseline = null;

	protected ?float $observed = null;

	protected ?DateTimeImmutable $windowAt = null;

	/**
	 * @param string $metric the name of the metric extractor, `p95` and the other
	 *     {@see PerfMetric} values among them
	 * @param DateTimeImmutable $windowAt the start of the window the regression was observed in
	 * @param DateTimeImmutable|null $date when it was recorded, now by default
	 */
	public function __construct(
		Project $project,
		string $path,
		string $metric,
		PerfUnit $unit,
		float $baseline,
		float $observed,
		DateTimeImmutable $windowAt,
		?DateTimeImmutable $date = null,
	) {
		parent::__construct($date);

		$this->project  = $project;
		$this->path     = $path;
		$this->metric   = $metric;
		$this->unit     = $unit;
		$this->baseline = $baseline;
		$this->observed = $observed;
		$this->windowAt = $windowAt;
	}

	public function getPath(): ?string
	{
		return $this->path;
	}

	public function getMetric(): ?string
	{
		return $this->metric;
	}

	public function getUnit(): ?PerfUnit
	{
		return $this->unit;
	}

	/** What the metric used to be, in the unit of the metric. */
	public function getBaseline(): ?float
	{
		return $this->baseline;
	}

	public function getObserved(): ?float
	{
		return $this->observed;
	}

	public function getWindowAt(): ?DateTimeImmutable
	{
		return $this->windowAt;
	}

	public function getMessage(): ?string
	{
		return sprintf(
			'%s on %s rose from %s to %s',
			$this->label(),
			$this->path,
			$this->unit?->format((float)$this->baseline),
			$this->unit?->format((float)$this->observed),
		);
	}

	public function getRequestUri(): ?string
	{
		return $this->path;
	}

	/**
	 * The same route regressing again is the same entry: the dashboard groups records by hash, so
	 * a regression that lasts an hour is one row with a count rather than twelve rows. The window
	 * is deliberately not part of it.
	 */
	public function calculateHash(): ?string
	{
		return md5(join('-', [$this->project?->getId()?->toHex(), $this->path, $this->metric]));
	}

	/**
	 * The ordinary record row. It prints the date, the project, how often it happened and
	 * {@see getMessage()}, which is the whole of what there is to say in a list.
	 */
	public function getComponentName(): string
	{
		return 'LogList:RecordLog';
	}

	public function isError(): bool
	{
		return true;
	}

	/** @return array<string, scalar|null> */
	#[Ignore]
	public function getMcpDetails(): array
	{
		return [
			'path'     => $this->path,
			'metric'   => $this->metric,
			'unit'     => $this->unit?->value,
			'baseline' => $this->baseline,
			'observed' => $this->observed,
			'windowAt' => $this->windowAt?->format('Y-m-d H:i:s'),
		];
	}

	/** The built-in metrics have a name worth reading; anything else is called what it is. */
	private function label(): string
	{
		return PerfMetric::tryFrom((string)$this->metric)?->label() ?? (string)$this->metric;
	}
}
