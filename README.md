![Tests](https://github.com/php-bug-catcher/bug-catcher/actions/workflows/symfony.yml/badge.svg)
[![Coverage Status](https://coveralls.io/repos/github/php-bug-catcher/bug-catcher/badge.svg?branch=main)](https://coveralls.io/github/php-bug-catcher/bug-catcher?branch=main)

# Catch every bug in all your PHP applications in one place

<p align="center">
<img src="docs/logo/default/horizontal.svg" width="600"><br>
</p>
<img src="docs/bug_catcher_01.png" width="800" >
<img src="docs/stacktrace.png" width="800" >

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
  `auto_prepend_file` line and no PHP extension, and reported as a record when a route regresses.
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
[php-bug-catcher/perf-collector](https://github.com/php-bug-catcher/perf-collector), point
`auto_prepend_file` at its hook and run `bc-perf-aggregate` from cron every minute. No PHP
extension, no daemon; the hook appends one line to a local file and the network call happens in the
cron run. Its README has the installation and the options.

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
