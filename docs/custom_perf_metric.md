## Custom performance metric

[Performance monitoring](../_docs/performance.md) watches one number per route and records a
`RecordPerformance` when it gets worse. Four metrics come with the bundle - `p95`, `avg`,
`error_rate` and `mem` - and `bug_catcher.perf.anomaly.metric` picks the one the shipped detector
watches.

Anything else your application can count is a metric too: time spent in SQL, cache misses, items
rendered, external API latency. It takes two things - the number has to be shipped from the
monitored machine, and something here has to know how to read it. This is the `Perf*` equivalent of
[custom_record.md](custom_record.md).

### What reaches the server

The number travels the same road a duration does, with one difference: nothing in the bundle knows
its name, so it stays in a bag called *extra* all the way.

```
$GLOBALS['_bcperf_extra']   the monitored application, during the request
  → "e": {"st": 0.0124}     one JSON line per request, written by the collector hook
  → bc-perf-aggregate       summed per (minute, machine, vhost, route), weighted by sample rate
  → POST /api/perf_buckets  rows[].extra
  → perf_bucket_extra       one row per bucket per name, added to by every later batch
  → WindowAggregate::$extra what a metric extractor reads
```

**On the monitored machine.** Merge numbers into `$GLOBALS['_bcperf_extra']` at any point during the
request; the hook reads it in shutdown, so the last value wins:

```php
// anywhere in the application - a kernel.terminate listener, a middleware, a destructor
$GLOBALS['_bcperf_extra']['sq'] = $queryCount;      // int
$GLOBALS['_bcperf_extra']['st'] = $querySeconds;    // float, rounded to 6 places by the hook
```

Only `int` and `float` values survive - the aggregator sums these and has nothing to do with a
string. Keep the names short: a sample line has a 4096 byte budget and `extra` is the first thing
thrown away when it does not fit. A name may be letters, digits, underscore, dot and dash, up to 32
characters (`BugCatcher\Entity\PerfBucket::EXTRA_NAME_PATTERN`); the ingest endpoint answers 422 for
anything else.

**On the server.** `PerfBucketUpserter` adds each name into `perf_bucket_extra`, and the repository
sums it over whatever window is being asked about. By the time an extractor sees it, the value is a
`float` **summed over the window**, exactly like `sumDuration` - per-hit numbers are the
extractor's job to compute. Durations are seconds and memory is bytes, the units the collector puts
on the wire.

### Implement the extractor

```php
namespace BugCatcher\Service\Perf\Detection;

interface MetricExtractorInterface
{
    public function name(): string;              // the key perf.metrics registers it under
    public function unit(): PerfUnit;            // Milliseconds | Ratio | Bytes
    public function extract(WindowAggregate $window): ?float;
}
```

A complete one, over the `st` the snippet above ships:

```php
<?php
// src/Perf/DbTimeExtractor.php
declare(strict_types=1);

namespace App\Perf;

use BugCatcher\Enum\PerfUnit;
use BugCatcher\Service\Perf\Detection\MetricExtractorInterface;
use BugCatcher\Service\Perf\WindowAggregate;

/** How long the average request of a route spent waiting for the database. */
final readonly class DbTimeExtractor implements MetricExtractorInterface
{
    public function name(): string
    {
        return 'db_time';
    }

    public function unit(): PerfUnit
    {
        return PerfUnit::Milliseconds;
    }

    public function extract(WindowAggregate $window): ?float
    {
        if ($window->hits === 0 || !isset($window->extra['st'])) {
            return null;
        }

        return $window->extra['st'] / $window->hits * 1000;
    }
}
```

Three rules behind those three methods:

- **`name()` has to equal the key it is registered under.** `MetricExtractorRegistry` compares them
  in its constructor and throws, so a mismatch fails the container build rather than production.
  The key is what the configuration says and `name()` is what ends up in the `metric` column of
  `record_performance`; two answers to one question would mean records nobody can find again.
- **`null` means "not computable here", not zero.** No hits, or nothing of yours in this window.
  The detector skips the route; zero would read as "it improved infinitely" and would be taken as a
  baseline of nothing.
- **`unit()` is semantics, not decoration.** It decides how the number is rendered on the record and
  in notifications (`PerfUnit::format()`), and how the threshold reads it - `min_absolute_ms` only
  applies to `PerfUnit::Milliseconds`.

`WindowAggregate` carries `hits`, `sumDuration`, `sumUser`, `sumSys`, `maxDuration`, `sumMem`,
`maxMem`, `clientErrors`, `serverErrors`, `durationHistogram` and `extra`, plus `errors()` and
`ok()`. A metric does not have to use `extra` at all - one over the histogram or the CPU sums is
just as valid.

### Register it and select it

```yaml
# config/packages/bug_catcher.yaml
bug_catcher:
    perf:
        metrics:
            db_time: App\Perf\DbTimeExtractor   # name => service id
        anomaly:
            metric: db_time
```

The map is merged *over* the built-in four, which is the same named-map idiom `PingCollectorCommand`
uses for its ping collectors - no tag, no compiler pass. Registering `p95` again replaces the
bundle's own.

`anomaly.metric` names one metric, because the shipped `RegressionDetector` watches one. Two metrics
at once means two detectors - see below.

### What happens then

`app:perf:detect` runs over the last completed minutes (`--window`, five by default), and for every
route of every enabled project it calls `extract()`, asks the baseline provider what that same route
did at the same time of day on previous same weekdays, and hands both numbers to the policy. What
survives becomes a `RecordPerformance`:

```
db_time on /checkout rose from 12 ms to 190 ms
```

`PerfMetric::tryFrom()` gives the four built-ins a readable label ("p95 latency"); a metric it does
not know is called by its own name, which is why a short, pronounceable `name()` is worth picking.
The unit is **stored on the record**, not looked up - the record has to still read as a sentence a
year later, when your extractor may no longer be configured.

From there it is an ordinary record: the dashboard row, the detail page, the notifier pipeline, and
MCP if you list `BugCatcher\Entity\RecordPerformance` in [`mcp.record_types`](mcp.md). It is
deduplicated by `md5(project id + path + metric)`, so a regression lasting an hour is one row whose
count goes up, not twelve rows - and a second metric on the same route is a record of its own.

### The other three seams

Same module, same philosophy: implement an interface, point configuration at it, never subclass.

**What "normal" is** - `BaselineProviderInterface`:

```php
public function baselineFor(
    Project $project,
    string $pathHash,
    PerfWindow $window,
    MetricExtractorInterface $metric,
): ?float;
```

The default (`DayOfWeekBaselineProvider`) takes the median of the same hour on the last
`baseline.lookback_weeks` same weekdays. A published SLO, a rolling mean or a number out of a config
file goes in its place by aliasing the interface, exactly the way `BatchRecordDeleteInterface` is
overridden:

```yaml
# config/services.yaml
BugCatcher\Service\Perf\Detection\BaselineProviderInterface: '@App\Perf\SloBaselineProvider'
```

**When to care** - `AnomalyPolicyInterface`:

```php
public function isAnomalous(float $baseline, float $observed, int $hits, PerfUnit $unit): bool;
```

The default (`ConjunctiveThresholdPolicy`) wants all of `anomaly.min_hits`, a rise, and a rise that
is large both relatively (`anomaly.factor`) and absolutely (`anomaly.min_absolute_ms`). Aliased the
same way.

**A detector of its own** - `PerfDetectorInterface`, for anything that is not "one metric against a
baseline": an SLO burn rate, a traffic drop, memory creeping up release after release.

```php
public function name(): string;

/** @return iterable<AnomalyFinding> */
public function detect(Project $project, PerfWindow $window): iterable;
```

```yaml
# config/packages/bug_catcher.yaml
bug_catcher:
    perf:
        detector_services:
            slo_burn: App\Perf\SloBurnDetector
        detectors: ['regression', 'slo_burn']
```

`detectors` is the list that actually runs; a name in it that no service answers to stops the
container build rather than quietly detecting nothing. Each `AnomalyFinding` the detector yields
(project, path, metric name, `PerfUnit`, baseline, observed, window start) is written as a
`RecordPerformance` by `RecordPerformanceWriter` - notifiers, MCP and the detail page follow with no
further code.

### Why nothing is detected yet

In order of how often it is the answer:

- **There is less than a week of history.** The baseline reads the *hour* bucket covering the same
  clock time on previous same weekdays - minute rows are kept for seven days and hour rows for
  ninety, so a baseline built from minutes would expire before `lookback_weeks` could use it. With
  no previous week, `baselineFor()` returns null and the route is skipped in silence.
- **The route is below `anomaly.min_hits`** (20 by default) in the window.
- **`min_absolute_ms` is a duration** and is therefore ignored for `PerfUnit::Ratio` and
  `PerfUnit::Bytes`. For those two the factor and the hit count carry the decision alone - set
  `factor` with that in mind.
- **`__other__` is never reported.** Beyond `perf.rollup_path_cap` distinct paths the roll-up folds
  the tail into that row, and "everything else got slower" is nothing anybody can open. The fix for
  seeing a path there is a normalisation rule in the collector, not a threshold.
- **Disabled projects are skipped.** Their buckets keep arriving until the collector on that machine
  is stopped, but nobody is watching them.
- **`bug_catcher.perf.enabled: false`** makes `app:perf:detect`, `app:perf:rollup` and
  `app:perf:purge` print an error and exit with a failure, and the ingest endpoint answer 503.
- **A name in `anomaly.metric` that no metric answers to** throws when the detector runs, listing
  the names it does know.
