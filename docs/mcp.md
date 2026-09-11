## MCP server

Bug Catcher can expose the errors it collected over the [Model Context Protocol](https://modelcontextprotocol.io),
so an AI assistant working in a client application can find the errors that application reported,
read their stack traces, and mark them resolved once it has fixed the cause.

The server speaks HTTP at `/mcp` and is guarded by a bearer token.

### Tools

| Tool | What it does |
|---|---|
| `list_projects` | The enabled projects and their codes. Everything else is filtered by one of those codes. |
| `search_records` | The distinct errors of a project. Filter by `status`, `code`, `minLevel` and a date range. |
| `get_record_detail` | One error in full: metadata, the stack trace as readable text, and the history of its occurrences. |
| `set_record_status` | Marks an error and every other occurrence of it as `resolved` or `archived`. |

Occurrences of one error are collapsed into a single entry, keyed by the record hash. `count` is how
often it happened in the range asked for, `date` is the latest occurrence, `firstOccurrence` the
earliest.

> `search_records` and `get_record_detail` cover `RecordLog` and its subclasses - everything the
> ingest API accepts. `RecordPing` is left out: an uptime check result is not a bug in a code base.
> A custom record type extending `Record` directly is not visible either, because the tools promise
> a `message` and a `level` such a class need not have.

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
