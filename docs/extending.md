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

## StatusList component

Your compoment should exend BugCatcher\Twig\Components\AbsComponent.

```yaml
# config/packages/bug_catcher.yaml
bug_catcher:
    status_list_components:
        - ProjectStatus
        - LogCount
        - LogSparkLine
        - YourStatusComponentName
```

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
| `.text-glow` | neon text shadow, follows the element's own colour. Dark theme only |

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
