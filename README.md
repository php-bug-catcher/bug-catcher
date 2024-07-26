![Tests](https://github.com/php-sentinel/bug-catcher/actions/workflows/symfony.yml/badge.svg)

# Catch every bug in all your PHP applications in one place

<p align="center">
<img src="docs/logo/default/horizontal.svg" width="600"><br>
</p>
<img src="docs/bug_catcher_01.png" width="800" >
<img src="docs/stacktrace.png" width="800" >

> [!CAUTION]
> **Work in progress, use at your own risk**

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

## Installation

**Create blank symfony project**

```bash
composer create-project symfony/skeleton:"7.1.*" bug-catcher
```

**Add depenedencies**


```bash
composer require php-sentinel/bug-catcher:dev-main
````

**Enable bundle if not already enabled**
```php
//config/bundles.php
return [
    ...
    PhpSentinel\BugCatcher\BugCatcherBundle::class => ['all' => true],
    ...
];
```
## Configuration
**setup packages**

```yaml
#config/services.yaml
parameters:
    logo: default #can be also 'blue', 'green' or 'red'
    refresh_interval: 60 # reload components every 60 seconds
```
```yaml
#config/packages/twig.yaml
twig:
    #...
    form_themes: [ '@EasyAdmin/symfony-form-themes/bootstrap_5_layout.html.twig' ]
    globals:
        logo: '%logo%'
        app_name: '%env(APP_NAME)%'
```
```yaml
#config/packages/twig_component.yaml
twig_component:
    #...
    defaults:
        #...
        PhpSentinel\BugCatcher\Twig\Components\: '@BugCatcher/components/'
```
```yaml
#config/packages/webpack_encore.yaml
webpack_encore:
    #...
    builds:
        bug_catcher: '%kernel.project_dir%/public/bundles/bugcatcher/'
framework:
    #...
    assets:
        packages:
            app:
                json_manifest_path: '%kernel.project_dir%/public/build/manifest.json'
            bug_catcher:
                json_manifest_path: '%kernel.project_dir%/public/bundles/bugcatcher/manifest.json'
```

**Security**

follow the instructions in the [Symfony docs](https://symfony.com/doc/current/security.html)
Modify these:
```yaml
#config/packages/security.yaml
security:
    #...
    providers:
        app_user_provider:
            entity:
                class: PhpSentinel\BugCatcher\Entity\User
                property: email
    firewalls:
        #...
        api:
            pattern: ^/api/
            stateless: true
        main:
            #...
            provider: app_user_provider
            form_login:
                login_path: bug_catcher.security.login
                check_path: bug_catcher.security.login
                enable_csrf: true
            logout:
                path: bug_catcher.security.logout
    access_control:
        - { path: ^/login$, role: PUBLIC_ACCESS }
        - { path: ^/api, roles: PUBLIC_ACCESS }
        - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/detail, roles: ROLE_DEVELOPER }
        - { path: ^/_components/LogList/clearAll, roles: ROLE_DEVELOPER }
        - { path: ^/_components/LogList/clearOne, roles: ROLE_DEVELOPER }
        - { path: ^/, roles: ROLE_CUSTOMER }
    role_hierarchy:
        ROLE_ADMIN: ROLE_DEVELOPER
        ROLE_DEVELOPER: ROLE_CUSTOMER
        ROLE_CUSTOMER: ROLE_USER
```
```yaml
#config/packages/doctrine.yaml
doctrine:
    orm:
        #...
        dql:
            string_functions:
                TYPE: PhpSentinel\BugCatcher\Extension\DQL\TypeFunction
```
```yaml
#config/packages/api_platform.yaml
api_platform:
    #...
    formats:
        #...
        json: [ 'application/json' ]
```
**Routes**

```yaml
#config/routes/bug_catcher.yaml
_bug_catcher:
    resource: "@BugCatcherBundle/config/routes.php"
    prefix:   /
```

**Download icons**

```bash
php bin/console ux:icons:import pajamas:hamburger covid:virus-lab-research-magnifier-1 clarity:archive-line game-icons:magic-broom
```

## First Run
**Create database**

```dotenv
# .env.local
APP_ENV=dev
APP_NAME=BugCatcher
CLEAR_STACKTRACE_ON_FIXED=true
DATABASE_URL=mysql://user:password@localhost:3306/bug_catcher
```
    
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console app:create-user username password
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

See package [php-sentinel/bug-catcher-reporter-bundle](https://github.com/php-sentinel/bug-catcher-reporter-bundle)

**Setup plain PHP applications**

See package [php-sentinel/bug-catcher-curl-reporter](https://github.com/php-sentinel/bug-catcher-curl-reporter)

## Modifications

See [docs/extending.md](docs/extending.md) for more information on how to extend the dashboard.

See [docs/custom_record.md](docs/custom_record.md) for more information on how to create custom record items.

See [docs/notifiers.md](docs/notifiers.md) for more information on how to create custom notifiers.

## Have Ideas, Feedback or an Issue?

If you have suggestions or questions, please feel free to
open an issue on this repository.

Have fun!
