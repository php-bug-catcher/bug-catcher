![Tests](https://github.com/php-bug-catcher/bug-catcher/actions/workflows/symfony.yml/badge.svg)
![Skeleton](https://github.com/php-bug-catcher/bug-catcher/actions/workflows/skeleton.yml/badge.svg)
[![Coverage Status](https://coveralls.io/repos/github/php-bug-catcher/bug-catcher/badge.svg?branch=main)](https://coveralls.io/github/php-bug-catcher/bug-catcher?branch=main)

# Catch every bug in all your PHP applications in one place

<p align="center">
<img src="docs/logo/default/horizontal.svg" width="600"><br>
</p>
<img src="docs/bug_catcher_01.png" width="800" >
<img src="docs/stacktrace.png" width="800" >

## Requirements

- **PHP 8.4 or newer**
- **MySQL 8.0+ or MariaDB 10.6+, running without `ONLY_FULL_GROUP_BY`.** Not a preference:
  `app:record-optimizer` buckets by `DATE_FORMAT()`/`SEC_TO_TIME()` and
  selects the row it grouped, and the performance ingest upserts with
  `INSERT ... ON DUPLICATE KEY UPDATE` and reads the row back with `LAST_INSERT_ID()`. PostgreSQL
  and SQLite have none of that. Set
  `sql_mode=STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`;
  the skeleton's `compose.yaml` already does.
- Node and Yarn, for the application's own Encore build.

## Installation

### By composer

```bash
composer create-project php-bug-catcher/skeleton your-project-name
````

### Manual

see [skeleton/readme.md](https://raw.githubusercontent.com/php-bug-catcher/skeleton/main/README.md)

## Features

- **Ping collector**. Ping Your projects in defined intervals to see if they are up and running.
- **Log viewer** with stack trace, code preview and history of all errors.
- **Custom records**. Create custom records to track any data you want.
- **Configurable Notification**. Get notified with favicon, sound email or sms if error count reaches configured threshold.
- **Access controll** Create users with acces to specific projects and its logs. You can add access to your client to see only specific part og logs.
- **Customizable**. You can add your own components to the dashboard.
- **Easy to use**. Just add a few lines of code to your project and you are ready to go.
- **Withholding**. You can hide errors until they reach a configured threshold.
- **Automatic cleanup**. Stack trace is optional and is cleaned up after the error is fixed.
- **MCP server**. Let an AI assistant working in your project list the errors it reported, read
  their stack traces and mark them resolved once it has fixed the cause. Served over HTTP at `/mcp`
  behind a bearer token. See [docs/mcp.md](docs/mcp.md).
- **Performance monitoring**. Wallclock, CPU and memory per request, collected with one
  `auto_prepend_file` line — or one `require` in the front controller, for hosting that has no
  `php.ini` to edit — and no PHP extension, and reported as a record when a route regresses.
  See [Performance monitoring](#performance-monitoring).

### Roadmap

- [x] Make it work
- [x] Create notification system
- [x] Create basic tests
- [x] Make more tests
- [x] Autoconfiguration
- [x] Create installer
- [x] Release first version
- [x] MCP server
- [ ] Scope MCP tokens to a user and their projects
- [ ] Email notification component
- [ ] Ping history graph component
- [ ] Errors history graph component
- [ ] Performance monitoring. The collector, the ingest endpoint, the roll-up, the retention purge
  and the regression records are in; charting it on the dashboard is not. See
  [Performance monitoring](#performance-monitoring).


## First Run
**Create database**

```dotenv
# .env.local
APP_ENV=dev
DATABASE_URL=mysql://user:password@localhost:3306/bug_catcher
```
    
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console app:create-user username password
yarn install
yarn build
```

**Start the built-in web server**

You can use Nginx or Apache, but the built-in web server works
great:

```
php bin/console server:run
```

Now check out the site at `http://localhost:8000`

**Setup cron for collection status codes**

```
# /etc/crontab
* * * * * www-data php /var/www/bug-catcher/bin/console app:ping-collector > /dev/null 2>&1
#optimize records by grouping them by 5 minutes older than 1 day
0 * * * * www-data php /var/www/bug-catcher/bin/console app:record-optimizer --past=1 --precision=5
#optimize records by grouping them by 60 minutes older than 7 days
0 0 * * * www-data php /var/www/bug-catcher/bin/console app:record-optimizer --past=7 --precision=60
```

With [performance monitoring](#performance-monitoring) in use, also:

```
*/5 * * * * www-data php /var/www/bug-catcher/bin/console app:perf:detect --window=5
5 * * * *   www-data php /var/www/bug-catcher/bin/console app:perf:rollup --granularity=hour
20 0 * * *  www-data php /var/www/bug-catcher/bin/console app:perf:rollup --granularity=day
40 0 * * *  www-data php /var/www/bug-catcher/bin/console app:perf:purge
```

The roll-up is what fills in every chart wider than two hours; without the hourly job those views
stay empty while the minute rows sit in the table.

## Enable Logging

**Setup your Symfony applications**

See package [php-bug-catcher/bug-catcher-reporter-bundle](https://github.com/php-bug-catcher/bug-catcher-reporter-bundle)

**Setup plain PHP applications**

See package [php-bug-catcher/bug-catcher-curl-reporter](https://github.com/php-bug-catcher/bug-catcher-curl-reporter)

## Performance monitoring

Bug Catcher collects what went wrong; this collects what went *slow*. Every request of a monitored
application contributes its wallclock, user and system CPU, peak memory and HTTP status, and when a
route gets measurably worse than it used to be, a record appears next to the errors - with the
notifications, the detail page and the MCP tools that every record gets.

**On the monitored machine**: install
[php-bug-catcher/perf-collector](https://github.com/php-bug-catcher/perf-collector), load its hook —
either by pointing `auto_prepend_file` at it, or by requiring it on the first line of the front
controller where there is no `php.ini` to edit — and run `bc-perf-aggregate` from cron every minute.
Both ways of loading it measure the same request, so the choice is only about what the hosting lets
you do. No PHP extension, no daemon; the hook appends one line to a local file and the network call
happens in the cron run. Its README has the installation and the options.

**On this server**, three commands keep the measurements useful. Add them next to the cron lines
above:

```
*/5 * * * *  php bin/console app:perf:detect
17   * * * *  php bin/console app:perf:rollup --granularity=hour
23   4 * * *  php bin/console app:perf:rollup --granularity=day && php bin/console app:perf:purge
```

- `app:perf:detect` looks at the minutes that have just finished - `--window` defaults to five and
  has to match the cron interval - and records the routes that regressed.
- `app:perf:rollup` computes hour buckets out of minutes and day buckets out of hours. Running it
  again over a window it has already done is a no-op, so a missed night is caught up with `--from`.
- `app:perf:purge` drops the buckets past their retention (minutes a week, hours ninety days, days
  two years by default). `--dry-run` counts them without deleting.

**The charts live at `/performance`**, not on the dashboard. The dashboard answers one question
from across the room - is anything on fire - and four charts of one project is a different
question, asked by somebody who has already picked a project out of that wall. There are two ways
in: the **Performance** button in the nav, which charts every project at once, and **clicking a
project's name** on the dashboard, which charts that one. The page is `/performance` and
`/performance/{project}`, so either is a link you can send to somebody.

```yaml
# config/packages/bug_catcher.yaml
bug_catcher:
    # the panels of /performance, in order. This is the default - say it only to change it
    performance_components:
        - PerfOverview      # throughput and latency, latency bands, where the time went, status mix
        - PerfTopPaths      # the heaviest routes, grouped and sorted like phptop
        - PerfDatabase      # queries per request, what they cost, and the routes that run most
    # the dashboard row of a project with performance switched off - unchanged
    status_list_components:
        - ProjectStatus
        - LogCount
        - LogSparkLine
        - WarningSound
    # and of one with it on. This is the default - say it only to change it
    perf_status_list_components:
        - ProjectStatus
        - LogCount          # the error count stays; it is what the dashboard has always been for
        - PerfApdex         # how many of the users waited
        - PerfLatency       # and how long the slow tenth of them waited
        - PerfSparkLine     # a day of p95, so the row shows a direction as well as a state
        - WarningSound
```

**Performance is per project**, a checkbox in the administration (`Project.perfEnabled`). A server
usually watches several applications and the collector gets installed on them one at a time, so
the dashboard draws each row the way that project is set up; a project with it off keeps the row
it always had. Off by default - a row that turned into three empty columns after an upgrade would
read as "this is broken".

With it on, the twelve columns of the row are shared out differently (the name gives up two,
the error count one), and the components are told so through a `dense` prop. A component of your
own that goes on both rows should honour it.

Two more cells are built and not in the default row, because adding one means taking a column
from something else: `PerfThroughput` (requests per minute - the one number here that *falls*
when an application stops answering at all, which every other cell makes look like a perfect
score) and `PerfRegressions` (how many routes `app:perf:detect` is currently shouting about,
measured against each route's own baseline rather than against a fixed threshold).

Listing a perf panel in `dashboard_components` is refused when the container is built: both lists
are resolved by component name, so a leftover entry would quietly keep drawing it on the homepage.

The charts are server-rendered SVG with no JavaScript, and they take their colours from the
`--bc-*` theme tokens, so they follow the light/dark switch like everything else.

**The roll-up is not optional if you want the wider windows.** A window up to two hours is read
from minute buckets, which the collector ships directly; anything wider reads hours, and hours
only exist once `app:perf:rollup` has run. Without that cron line the 24 h and 7 d views are
empty even though the measurements are in the database. The detail page of a
regression draws the route around the time it happened with the baseline across it
(`Detail:PerfChart`, registered for `RecordPerformance` by default).

**`PerfDatabase` needs the Symfony bundle, not just the hook.** The hook cannot see a query - it
runs before your autoloader and knows nothing about Doctrine. What fills that panel is
[`php-bug-catcher/perf-collector-bundle`](https://github.com/php-bug-catcher/perf-collector-bundle),
whose DBAL middleware counts and times every query and merges `sq` and `st` into
`$GLOBALS['_bcperf_extra']`; the collector sums them per bucket and they are stored in
`perf_bucket_extra`. Without it the panel says so rather than drawing an empty chart. Anything
else an application merges into that global is stored the same way and is available to a custom
metric extractor - see [docs/custom_perf_metric.md](docs/custom_perf_metric.md).

Everything is configured under `bug_catcher.perf` - `enabled`, `retention`, `rollup_path_cap`,
`anomaly`, `baseline`, `metrics`, `detectors`. The design is in
[docs/performance.md](_docs/performance.md), and
[docs/custom_perf_metric.md](docs/custom_perf_metric.md) is the step-by-step for detecting
regressions on a metric of your own.

## Modifications

See [docs/extending.md](docs/extending.md) for more information on how to extend the dashboard.

See [docs/custom_record.md](docs/custom_record.md) for more information on how to create custom record items.

See [docs/notifiers.md](docs/notifiers.md) for more information on how to create custom notifiers.

See [docs/mcp.md](docs/mcp.md) for how to let an AI assistant read and resolve the collected errors
over the Model Context Protocol.

See [docs/custom_perf_metric.md](docs/custom_perf_metric.md) for how to detect performance
regressions on a metric of your own.

## Have Ideas, Feedback or an Issue?

If you have suggestions or questions, please feel free to
open an issue on this repository.

Have fun!
