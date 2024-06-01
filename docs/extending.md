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

## Custom Record Item

You can create your own Log item. You need create Entity extending ```PhpSentinel\BugCatcher\Entity\Record``` .
After that, you need to crete configuration for modify Record entity to register
[discriminator map](https://www.doctrine-project.org/projects/doctrine-orm/en/3.2/reference/inheritance-mapping.html#line-number-e541161234d47fae4bc4a6f94cf602c400e585ab-29).
You need also create Repository for this entity extending ```PhpSentinel\BugCatcher\Repository\RecordRepositoryInterface```.

Now is only need to add to configuration

```yaml
# config/services.yaml
parameters:
    dashboard_list_items:
        - RecordLog::class
        - RecordLogTrace::class
        - YourRecord::class
``