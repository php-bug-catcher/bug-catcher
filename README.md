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

### Roadmap

- [x] Make it work
- [x] Create notification system
- [x] Create basic tests
- [x] Make more tests
- [x] Autoconfiguration
- [x] Create installer
- [x] Release first version
- [ ] Email notification component
- [ ] Ping history graph component
- [ ] Errors history graph component


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

## Modifications

See [docs/extending.md](docs/extending.md) for more information on how to extend the dashboard.

See [docs/custom_record.md](docs/custom_record.md) for more information on how to create custom record items.

See [docs/notifiers.md](docs/notifiers.md) for more information on how to create custom notifiers.

## Have Ideas, Feedback or an Issue?

If you have suggestions or questions, please feel free to
open an issue on this repository.

Have fun!
