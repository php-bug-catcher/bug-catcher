## MCP server

Bug Catcher can expose the errors it collected over the [Model Context Protocol](https://modelcontextprotocol.io),
so an AI assistant working in a client application can find the errors that application reported,
read their stack traces, and mark them resolved once it has fixed the cause - and read everything the
[performance page](performance.md) shows about it.

The server speaks HTTP at `/mcp` and is guarded by a bearer token.

### Tools

| Tool | What it does |
|---|---|
| `list_projects` | The enabled projects and their codes, and whether performance monitoring is on for each (`perfEnabled`, `perfProfile`). Everything else is filtered by one of those codes. |
| `search_records` | The distinct errors of a project. Filter by `status`, `code`, `minLevel`, `type` and a date range. |
| `get_record_detail` | One error in full: metadata, the stack trace as readable text, the type's own `details`, and the history of its occurrences. |
| `set_record_status` | Marks an error and every other occurrence of it as `resolved` or `archived`. |
| `get_perf_health` | A project's dashboard row: hits, requests per minute, p95, Apdex and its rating, error rate, open regressions. Without `projectCode`, every monitored project - the place to start. |
| `get_perf_top_paths` | The heaviest routes, vhosts or machines (`groupBy`), sorted the way the page sorts (`sortBy`). `pathContains` finds a route or script by part of its path; `host` and `server` narrow to one. |
| `get_perf_path` | One route in full - latency, CPU and waiting, memory, status mix, latency bands, database - and the same per machine and per vhost. |
| `get_perf_timeseries` | The charts as numbers, bucket by bucket, for a project, a route, a vhost or a machine. |
| `get_perf_database` | Queries and time in the database per request, its share of the response, and the routes that spend the most there. |
| `get_perf_regression` | A regression the detector found, with its route around the time it was found. |

Occurrences of one error are collapsed into a single entry, keyed by the record hash. `count` is how
often it happened in the range asked for, `date` is the latest occurrence, `firstOccurrence` the
earliest.

### Performance

The `get_perf_*` tools read through the same report builder the performance page draws, so the
numbers are the page's numbers. They only read; a regression is resolved with `set_record_status`
like any other record. While `bug_catcher.perf.enabled` is false every one of them refuses with a
message saying so.

**The window** is the page's `?at=&hours=`: `hours` long (1 by default, clamped to a year), starting
at `at`, or ending now when `at` is left out. Unlike the page, an `at` that cannot be read is refused
rather than quietly replaced by the live window - a person sees the chart move, an assistant would
report on the wrong hour. `at` is read in the server's timezone when it names none, and moved into it
when it does, because the stored times carry none. Every answer says which `window` it read, rounded
to buckets: minutes up to two hours, hours up to seven days, days beyond.

**Anything wider than two hours needs `app:perf:rollup`** to have run. An empty answer at hour or day
granularity carries a `note` saying so, because the reader cannot see that the minute rows are there.

**Units are in the key**: `Ms` is milliseconds, `Bytes` bytes, and `errorRate`, `apdex` and
`shareOfResponse` are ratios from 0 to 1. Per-request numbers and totals are both there - `avgMs` and
`totalMs` - since the page's "per hit" toggle is presentation. `database` is null where nothing
counted queries, which means `php-bug-catcher/perf-collector-bundle` is missing from the monitored
application, not that the route runs none. `waitMs` is null on a machine that reports no CPU at all.

**Finding a script**: `get_perf_top_paths` with `pathContains: "import"` matches every route whose
path contains it, case-insensitively and literally - a `%` or `_` is a character, not a wildcard.
`get_perf_path`, `get_perf_timeseries` and `get_perf_database` take `path` exactly, as a `label` of
that list spells it for one project; across projects the label is prefixed with the project code.

**Apdex** is at T = 500 ms. A project whose `profile` is `worker` - queue consumers and cron jobs -
reads near zero by design; judge it by `hits` and `openRegressions` instead, as its dashboard row
does.

### Which record types the tools see

`Record` is a hierarchy an application extends, and what belongs on an MCP server is a decision, not
a consequence. The searchable types are listed in `bug_catcher.mcp.record_types`:

```yaml
bug_catcher:
    mcp:
        record_types:
            - BugCatcher\Entity\RecordLog
            - BugCatcher\Entity\RecordLogTrace
            - BugCatcher\Entity\RecordPerformance
            - App\Entity\RecordCron       # your own type
```

The default is `RecordLog`, `RecordLogTrace` and `RecordPerformance` - everything the ingest API
accepts out of the box, and the regressions `app:perf:detect` writes, which `search_records` finds
under the type `perf`. An installation that lists `record_types` itself does not get
`RecordPerformance` by upgrading; add it to the list to let `get_perf_regression` start from
`search_records`.
Subclasses of a listed class come along, the way `INSTANCE OF RecordLog` would also match a traced
log. A class that is not in the discriminator map of `Record` is refused with an exception naming the
ones that are, rather than silently never matching.

`RecordPing` is not listable: an uptime check result is not a bug in a code base, and it has neither
a hash to group by nor a component name to render.

This is deliberately *not* `dashboard_list_items`. An assistant holding the MCP token can resolve and
archive what it finds, so adding a type here is a decision to take on its own, not a side effect of
putting that type on a page.

Every entry reports the `type` it is - the Doctrine discriminator value - and `search_records` takes
a `type` argument to narrow to one of them. The valid set is per instance, so it cannot be an enum in
the tool schema; an unknown value is answered with the list this server does search.

A type that carries no monolog level reports `level` null, and `minLevel` leaves it out entirely -
asking for a level floor asks for the records that have a level at all.

#### Fields of your own

`message` and `requestUri` are answered by `Record` for every type: null unless the subtype overrides
them, which a computed message (why is this cron run late?) usually does.

Anything beyond that arrives under the `details` key of `get_record_detail`, for a type that
implements `BugCatcher\Mcp\HasMcpDetails`:

```php
class RecordCron extends Record implements HasMcpDetails
{
    public function getMcpDetails(): array {
        return [
            'command'          => $this->command,
            'lastStart'        => $this->lastStart?->format('Y-m-d H:i:s'),
            'runtimeSeconds'   => $this->runtimeSeconds(),
            'estimatedSeconds' => $this->estimated,
        ];
    }
}
```

Flat and already printable - the client reads JSON. `details` is null for a type that does not
implement it, and the key is always present. See [custom_record.md](custom_record.md).

### Installation

The bundle pulls in `symfony/mcp-bundle` and `nyholm/psr7`. Flex registers the bundle; the
configuration below comes from the recipe in `config/recipes/`.

`config/packages/mcp.yaml`:

```yaml
mcp:
  servers:
    bug_catcher:
      name: 'bug-catcher'
      transports:
        http: true
      http:
        path: /mcp
        allowed_hosts: '%env(csv:MCP_ALLOWED_HOSTS)%'
      registry:
        tools: ['BugCatcher\Mcp\Tool\']
```

`config/routes/mcp.yaml`:

```yaml
mcp:
  resource: .
  type: mcp
```

`config/packages/security.yaml` - a firewall of its own, since `^/api` runs on `PUBLIC_ACCESS` and
the MCP endpoint must not:

```yaml
security:
  firewalls:
    mcp:
      pattern: ^/mcp
      stateless: true
      access_token:
        token_handler: BugCatcher\Security\McpAccessTokenHandler
  access_control:
    - { path: ^/mcp, roles: ROLE_MCP }
  role_hierarchy:
    ROLE_MCP: ROLE_DEVELOPER
```

### Configuration

```yaml
bug_catcher:
  mcp:
    access_token: '%env(default::MCP_ACCESS_TOKEN)%'   # the default
    record_types:                                      # the default
      - BugCatcher\Entity\RecordLog
      - BugCatcher\Entity\RecordLogTrace
      - BugCatcher\Entity\RecordPerformance
```

Two environment variables:

```dotenv
# Whoever holds this reads the errors of every project on the instance. Generate it with
# `php -r 'echo bin2hex(random_bytes(32));'` and keep it out of version control.
MCP_ACCESS_TOKEN=...

# DNS rebinding protection. The SDK accepts "localhost" alone by default, so a deployed instance
# has to name its own host or every request is refused.
MCP_ALLOWED_HOSTS=bugcatcher.example.com
```

**An unset or blank `MCP_ACCESS_TOKEN` refuses every request.** That is deliberate: the alternative
would publish the whole instance on the day someone forgets to set it.

Check the result with:

```bash
php bin/console debug:mcp
```

which lists the ten tools and the schema of their arguments.

### Connecting a client

```bash
claude mcp add --transport http bug-catcher https://bugcatcher.example.com/mcp \
    --header "Authorization: Bearer <token>"
```

Any MCP client speaking the streamable HTTP transport works the same way; the token goes in an
`Authorization: Bearer` header.

### A word on resolving

`set_record_status` with `resolved` clears the stack trace of the group when
`bug_catcher.clear_stacktrace_on_fixed` is left at its default of `true`. The trace is the one part
that cannot be recovered afterwards, so read the error with `get_record_detail` before resolving it.
The tool descriptions say as much, and assistants do follow it - but it is worth knowing when you
read a resolved record later and find the trace gone.

### Scope of the token

The token is not tied to a `User`, so it is not scoped to any project: whoever holds it reads every
project on the instance. Narrowing it to a user's `getActiveProjects()` is the next step and is not
implemented yet. Until it is, treat the token as an instance-wide read-and-resolve credential.
