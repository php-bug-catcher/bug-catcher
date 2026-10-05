<?php

declare(strict_types=1);

namespace BugCatcher\Twig\Components;

use BugCatcher\Service\Perf\Report\Dto\PerfRange;
use BugCatcher\Service\Perf\Report\PerfReportBuilder;
use BugCatcher\Service\Perf\Report\PerfRangeResolver;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * The window controls every panel on `/performance` has: which stretch of time, and which route.
 *
 * The three panels are deliberately independent - each one keeps its own window, the way each
 * already keeps its own `sort` and `group` - so this is shared code rather than shared state.
 * `PerformanceController` seeds all three from the URL on the first render and they diverge from
 * there.
 *
 * A trait rather than a base class because these are Live Components and already use one
 * ({@see \Symfony\UX\LiveComponent\DefaultActionTrait}); the `LiveProp` attributes are read by
 * reflection over the composed class, so declaring them here is the same as declaring them there.
 */
trait PerfRangeProps
{
	/**
	 * Where the window starts, as {@see PerfRange::AT_FORMAT} spells it.
	 *
	 * Null means the window follows the clock and ends now, which is the only thing a panel could
	 * do before there was an anchor - so an installation that never touches a picker sees exactly
	 * what it saw before.
	 */
	#[LiveProp(writable: true)]
	public ?string $at = null;

	/**
	 * One route rather than the whole project.
	 *
	 * What the link on a regression narrows the page to: arriving from a `RecordPerformance` and
	 * being shown the whole application's traffic answers a question nobody asked.
	 */
	#[LiveProp(writable: true)]
	public ?string $path = null;

	/** How long the window is. The report picks the bucket width from it. */
	#[LiveProp(writable: true)]
	public int $hours = PerfRangeResolver::DEFAULT_HOURS;

	private ?PerfRange $range = null;

	/** Memoised: a template asks for the window, and then for every chart drawn over it. */
	public function getRange(): PerfRange
	{
		return $this->range ??= $this->ranges()->resolve($this->at, $this->hours);
	}

	/** @return array<int, string> the lengths the quick control offers, in hours */
	public function getWindows(): array
	{
		return PerfReportBuilder::WINDOW_HOURS;
	}

	/**
	 * The resolver, from the composing component's constructor.
	 *
	 * A trait cannot have its own constructor-promoted dependency, and a component that forgot to
	 * wire this should fail to compile rather than resolve a window from nothing.
	 */
	abstract protected function ranges(): PerfRangeResolver;
}
