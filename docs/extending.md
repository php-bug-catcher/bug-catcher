# BugCatcher

All elements on dashboard page is based in [Twig Components](https://symfony.com/bundles/ux-twig-component/current/index.html)
You are free to create your own components and use them in your dashboard.

Components is defined in ```config/services.yaml``` as array of component names.

## Dashboard module

Dashboard is based on rows. Each row is Twig component.
You can create your own row component and use it in your dashboard.

Only one parameter ```string status``` is passed to this component. Its determine which status should be displayed on dashboards.

```yaml
# config/packages/bug_catcher.yaml
bug_catcher:
    dashboard_components:
        - StatusList
        - LogList
```

The three performance panels are **not** dashboard components - they are the page at
`/performance`, configured under `performance_components`, and listing one here is refused when
the container is built:

```yaml
bug_catcher:
    performance_components:
        - PerfOverview      # four charts of one project's window
        - PerfTopPaths      # the heaviest routes, with phptop's flags as controls
        - PerfDatabase      # queries per request and what they cost
        - MyPanel           # your own, by component name, same as dashboard_components
```

A panel of your own takes a `#[LiveProp] public ?Project $project` and has to answer null as
"every project at once" - the page passes null until somebody picks one. See the
[README](../README.md#performance-monitoring).

### The window a panel is asked about

The page also passes `at`, `hours` and `path`, read from its own query string. A panel that does not
declare them receives them as HTML attributes through `attributes` rather than failing, so an
existing custom panel keeps working - it just always shows the last hour.

To honour them, use the trait the three built-in panels use. It brings the three `LiveProp`s and
turns them into one window, so that a link from a regression lands on the same stretch of time in
every panel on the page:

```php
use BugCatcher\Service\Perf\Report\PerfRangeResolver;
use BugCatcher\Twig\Components\PerfRangeProps;

#[AsLiveComponent]
final class MyPanel
{
    use DefaultActionTrait;
    use PerfRangeProps;

    #[LiveProp]
    public ?Project $project = null;

    public function __construct(
        private readonly PerfReportBuilder $report,
        private readonly PerfRangeResolver $rangeResolver,
    ) {
    }

    public function getSeries(): PerfTimeSeries
    {
        $range = $this->getRange();

        return $this->report->timeSeries($this->project, $range->from, $range->to, $this->path);
    }

    protected function ranges(): PerfRangeResolver
    {
        return $this->rangeResolver;
    }
}
```

`getRange()` returns a `PerfRange` of `from`, `to` and `live`; `$path` is null for the whole project
or one route. Three things are worth knowing:

- **`at` is the start of the window**, `[at, at + hours)`, formatted `Y-m-d\TH:i`. Absent means the
  window follows the clock and ends now. If you want a moment *centred*, subtract half the window
  when you build the link - which is what `Detail:PerfChart` does.
- **`hours` is clamped** to between 1 and `PerfRangeResolver::MAX_HOURS` (one year), and an `at`
  that does not parse falls back to the live window. Neither is your panel's problem; the resolver
  has already dealt with it by the time you see a `PerfRange`.
- **Granularity is not a control.** `PerfReportBuilder` picks minute, hour or day from the width of
  the window. Anything wider than two hours is read from rolled-up buckets, so it stays empty until
  `app:perf:rollup` has run.

To draw the control itself, include the partial the built-in panels share:

```twig
{% include '@BugCatcher/components/_perf_range.html.twig' with {
    range: this.range, windows: this.windows, selected: this.hours
} only %}
```

## StatusList component

Your compoment should exend BugCatcher\Twig\Components\AbsComponent.

```yaml
# config/packages/bug_catcher.yaml
bug_catcher:
    status_list_components:
        - ProjectStatus
        - LogCount
        - LogSparkLine
        - PerfSparkLine
        - YourStatusComponentName
```

`PerfSparkLine` is the latency twin of `LogSparkLine`: a day of p95 per project, also off by
default.

Both lines are drawn against a **fixed** scale, so the colour means the same thing on every card:
`LogSparkLine` tops out at its `treshold` prop (5 errors per quarter-hour by default, settable from
a template), `PerfSparkLine` at four times Apdex T. A value over the top clamps there rather than
running off the chart. To draw one of your own, the wrapper is
`BugCatcher\Service\SparkLine\SparkLineRenderer` - hand it a value per slot and a
`SparkLineScale`, and fill the buckets nothing was reported for with `SparkLineSlots`
(`SparkLineGapMode::Zero` for a counter, `::Hold` for a gauge).

## Detail page components

When you have cusoim record item, you can create your own detail page components.
First founded class in order by instance of record item is used as detail page component.

```yaml
# config/packages/bug_catcher.yaml
bug_catcher:
    detail_components:
        BugCatcher\Entity\YourRecord:
            - Detail:Header
            - Detail:Title
            - Detail:HistoryList
            - YourComponentName
        BugCatcher\Entity\RecordLogTrace:
            - Detail:Header
            - Detail:Title
            - Detail:HistoryList
            - Detail:StackTrace
        BugCatcher\Entity\RecordLog:
            - Detail:Header
            - Detail:Title
            - Detail:HistoryList
        BugCatcher\Entity\RecordPerformance:
            - Detail:Header
            - Detail:Title
            - Detail:HistoryList
            - Detail:PerfChart
```

## Custom Ping collector

Create your class extending ```BugCatcher\Service\PingCollector\PingCollectorInterface```

```php
namespace App\Service;

use BugCatcher\Entity\Project;
use BugCatcher\Service\PingCollector\PingCollectorInterface;
use Symfony\Component\HttpFoundation\Response;

class OkPingCollector implements PingCollectorInterface {

	public function ping(Project $project): string {
		return Response::HTTP_OK;
	}
}
```

Add it to configurations:
```yaml
# config/packages/bug_catcher.yaml
bug_catcher:
    #...
    collectors:
        Http: http
        Messenger: messenger
        Always Ok: always_ok
        None: none
services:
    #...
    BugCatcher\Command\PingCollectorCommand:
        arguments:
            $collectors:
                http: '@BugCatcher\Service\PingCollector\HttpPingCollector'
                messenger: '@BugCatcher\Service\PingCollector\MessengerCollector'
                always_ok: '@App\Service\OkPingCollector'
```

## Styling your components

The dashboard is built with Tailwind CSS v4 on a small set of design tokens. Bootstrap is not
available - `btn btn-primary`, `badge`, `row`, `col-*` and friends will not do anything.

Colours are CSS custom properties swapped by theme and exposed to Tailwind, so you write one
class and it works in both light and dark. Do not pair utilities with `dark:` variants for
colour; that is what the tokens are for.

| Utility | Use |
|---|---|
| `bg-bg`, `bg-surface`, `bg-surface-2` | page, card, raised surfaces |
| `text-fg`, `text-muted` | body text, secondary text |
| `border-line` | every border |
| `text-accent`, `text-ok`, `text-warn`, `text-danger` | status colours |

Component classes, so you do not repeat long class strings:

| Class | What it is |
|---|---|
| `.panel` | the card surface. `.panel-glow` adds the neon ring, `.panel-accent` a gradient top edge |
| `.chip` + `.chip-ok` / `.chip-warn` / `.chip-danger` / `.chip-info` / `.chip-accent` | small labels |
| `.btn` + `.btn-ghost` / `.btn-accent`, with `.btn-sm` / `.btn-icon` | buttons |
| `.input` | form controls |
| `.led` + `.led-ok` / `.led-danger` | pulsing status light |
| `.code` | the stack trace listing |
| `.bc-hint` | the info dot that explains a panel on hover and on focus. Add `.bc-hint--end` at the right edge of a panel so the bubble opens inwards |
| `.text-glow` | neon text shadow, follows the element's own colour. Dark theme only |

The hint has a partial of its own, so a component does not repeat the markup:

```twig
{% include '@BugCatcher/components/_hint.html.twig' with {
    text: 'What this chart means.'|trans({}, 'BugCatcher')
} only %}
```

A project with `perfEnabled` set draws its row from one of two other lists instead -
`perf_status_list_components` for a web project and `worker_status_list_components` for one whose
`perfProfile` is `Worker`, which is a cron box or a messenger consumer with nobody waiting on it.
Either way every component is told so through a `dense` prop: the same twelve columns then have
five cells to share rather than three. Honour it if your component goes on more than one row:

```twig
<div{{ attributes.defaults({class:(dense ? 'col-span-1' : 'col-span-2') ~ ' text-right'}) }}>
```

Write both class names out rather than building one: Tailwind finds classes by scanning these
files as text, and `'col-span-' ~ n` is not a class name it can see.

A status list component renders inside a 12 column grid and **must carry its own width**, because
the component list is configurable and the row cannot know what it will contain:

```twig
<div{{ attributes.defaults({class:'col-span-3 flex items-center gap-1.5'}) }}>
    <span class="led led-ok"></span>
    <span class="truncate text-sm">{{ project.name }}</span>
</div>
```

The built-in components use 6 (`ProjectStatus`), 4 (`LogSparkLine`) and 2 (`LogCount`) columns, so
pick spans that still add up to 12 once yours is in the list.

Two things worth knowing:

- Tailwind only emits classes it can find as literal text. Building a class name from a variable
  (`text-{{ color }}`) produces nothing - map the full strings in the template instead.
- Twig templates live outside `assets/`, so they are registered with `@source` in
  `assets/styles/app.css`. Webpack does not watch them: after editing a template, run `yarn dev`
  or `yarn build` before expecting new classes to exist.

The dashboard is often shown on a wall monitor that nobody interacts with, so prefer density -
one line per row, truncate rather than wrap, and keep colour for things that need attention.
