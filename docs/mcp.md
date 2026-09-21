## MCP server

Bug Catcher can expose the errors it collected over the [Model Context Protocol](https://modelcontextprotocol.io),
so an AI assistant working in a client application can find the errors that application reported,
read their stack traces, and mark them resolved once it has fixed the cause.

The server speaks HTTP at `/mcp` and is guarded by a bearer token.

### Tools

| Tool | What it does |
|---|---|
| `list_projects` | The enabled projects and their codes. Everything else is filtered by one of those codes. |
| `search_records` | The distinct errors of a project. Filter by `status`, `code`, `minLevel`, `type` and a date range. |
| `get_record_detail` | One error in full: metadata, the stack trace as readable text, the type's own `details`, and the history of its occurrences. |
| `set_record_status` | Marks an error and every other occurrence of it as `resolved` or `archived`. |

Occurrences of one error are collapsed into a single entry, keyed by the record hash. `count` is how
often it happened in the range asked for, `date` is the latest occurrence, `firstOccurrence` the
earliest.

### Which record types the tools see

`Record` is a hierarchy an application extends, and what belongs on an MCP server is a decision, not
a consequence. The searchable types are listed in `bug_catcher.mcp.record_types`:

```yaml
bug_catcher:
    mcp:
        record_types:
            - BugCatcher\Entity\RecordLog
            - BugCatcher\Entity\RecordLogTrace
            - App\Entity\RecordCron       # your own type
```

The default is `RecordLog` and `RecordLogTrace` - everything the ingest API accepts out of the box.
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

which lists the four tools and the schema of their arguments.

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
