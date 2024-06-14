# BugCatcher

All elements on dashboard page is based in [Twig Components](https://symfony.com/bundles/ux-twig-component/current/index.html)
You are free to create your own components and use them in your dashboard.

Components is defined in ```config/services.yaml``` as array of component names.

## Dashboard module

Dashboard is based on rows. Each row is Twig component.
You can create your own row component and use it in your dashboard.

Only one parameter ```string status``` is passed to this component. Its determine which status should be displayed on dashboards.

```yaml
# config/services.yaml
parameters:
    dashboard_components:
        - StatusList
        - LogList
```

## StatusList component

Your compoment should exend PhpSentinel\BugCatcher\Twig\Components\AbsComponent.

```yaml
# config/services.yaml
parameters:
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
# config/services.yaml
parameters:
    detail_components:
        PhpSentinel\BugCatcher\Entity\YourRecord:
            - Detail:Header
            - Detail:Title
            - YourComponentName
        PhpSentinel\BugCatcher\Entity\RecordLogTrace:
            - Detail:Header
            - Detail:Title
            - Detail:StackTrace
        PhpSentinel\BugCatcher\Entity\RecordLog:
            - Detail:Header
            - Detail:Title
```

## Custom Ping collector

Create  your class extending ```PhpSentinel\BugCatcher\Service\PingCollector\PingCollectorInterface```

```php
namespace App\Service;

use PhpSentinel\BugCatcher\Entity\Project;
use PhpSentinel\BugCatcher\Service\PingCollector\PingCollectorInterface;
use Symfony\Component\HttpFoundation\Response;

class OkPingCollector implements PingCollectorInterface {

	public function ping(Project $project): string {
		return Response::HTTP_OK;
	}
}
```

Add it to configurations:
```yaml
#config/services.yaml
parameters:
    #...
    collectors:
        Http: http
        Messenger: messenger
        Always Ok: always_ok
        None: none
services:
    #...
    PhpSentinel\BugCatcher\Command\PingCollectorCommand:
        arguments:
            $collectors:
                http: '@PhpSentinel\BugCatcher\Service\PingCollector\HttpPingCollector'
                messenger: '@PhpSentinel\BugCatcher\Service\PingCollector\MessengerCollector'
                always_ok: '@App\Service\OkPingCollector'
```
