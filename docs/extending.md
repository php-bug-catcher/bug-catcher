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
