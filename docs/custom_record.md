## Custom Record Item

You are free to create your own record item.
Just create class ```App\Entity\MyRecord``` extends ```PhpSentinel\BugCatcher\Entity\Record``` class.

When you have custom class you need registered it to discriminator map.
This is little bit tricky, because you need override all ORM mappings.

### Copy ORM files

**Windows**

In windows you need copy orm files to your project and edit it.
Be careful when tou update bug-catcher package, you need copy all this files again and merge your file.
Remember, some files can be added or edited in new version.

```
mkdir config/doctrine/BugCatcherBudnle
cp vendor/php-sentinel/bug-catcher/config/doctrine/* config/doctrine/BugCatcherBudnle
```

**Linux**

In linux you can create symbolic links to original files and override only one file.
So you can update bug-catcher package without any changes in your project except one file that you edited.

But you need copy simlinks again sometimes after composer update because some files can be added in new version.

```
mkdir config/doctrine/BugCatcherBudnle
find ./vendor/php-sentinel/bug-catcher/config/doctrine/ -name '*.xml' -exec bash -c 'ln -rs "$0" ./config/doctrine/BugCatcherBundle/' {} \;
rm config/doctrine/BugCatcherBundle/Record.orm.xml
cp vendor/php-sentinel/bug-catcher/config/doctrine/Record.orm.xml config/doctrine/BugCatcherBundle/Record.orm.xml
```

### Configure ORM

```xml
<!--config/doctrine/BugCatcherBudnle/Record.orm.xml-->
<!--...-->
<discriminator-map>
    <!--...-->
    <discriminator-mapping value="my-record" class="App\Entity\MyRecord"/>
</discriminator-map>
<!--...-->
```

```yaml
# config/packages/doctrine.yaml
doctrine:
    #...
    orm:
        #...
        mappings:
            BugCatcherBundle:
                type: xml
                dir: '%kernel.project_dir%/config/doctrine/BugCatcherBundle/'
                prefix: 'PhpSentinel\BugCatcher\Entity'
                alias: PhpSentinelBundle
```

**Enable Api**

You need enable api plaform for your custom entity. See [Api Platform documentation](https://api-platform.com/docs/core/getting-started/) for more
details.
You can inspire in entity [PhpSentinel\BugCatcher\Entity\RecordLog](../src/Entity/RecordLog.php) for more details.

### Register Record Item

Now you can create twig component ```MyRecord``` for rendering log row in dashboard. and configure it in services.yaml

```yaml
# config/services.yaml
parameters:
    dashboard_list_items:
        - RecordLog::class
        - RecordLogTrace::class
        - MyRecord::class
```

### Send log to BugCatcher

If you have installed [php-sentinel/bug-catcher-reporter-bundle](https://github.com/php-sentinel/bug-catcher-reporter-bundle) in your project,
you can now send your custom log record

```php
class TestCommand extends Command {
	public function __construct(private readonly BugCatcherInterface $bugCatcher) {
		parent::__construct();
	}
	protected function execute(InputInterface $input, OutputInterface $output): int {

		$this->bugCatcher->log([
			"api_uri"=>"/api/record_cron",
			"command"=>"foo",
			"lastStart"=>(new \DateTime("-10minutes"))->format(\DateTime::RFC3339_EXTENDED),
			"estimated"=>60,
		]);
		return Command::SUCCESS;
	}
}
```