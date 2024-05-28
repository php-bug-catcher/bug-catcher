# Catch every bug in all your Symfony applications in one place

![screenshot](docs/bug_catcher_01.png)
![screenshot](docs/stacktrace.png)

## Setup

**Download the code**

```
git clone https://github.com/php-sentinel/bug-catcher.git
```

To get it working, follow these steps:

**Download Composer dependencies**

Make sure you have [Composer installed](https://getcomposer.org/download/)
and then run:

```
composer install
```

You may alternatively need to run `php composer.phar install`, depending
on how you installed Composer.

**Setup the database**

```dotenv
# /env.local
APP_ENV=prod
DATABASE_URL=mysql://username:password@127.0.0.1:3306/database-name
```

Run migrate command for setup database

```
php bin/console doctrine:migrations:migrate
```

**build javascripts**

You need to have downloaded and installed
[nodejs](https://nodejs.org/en) and
[yarn](https://classic.yarnpkg.com/en/docs/install#debian-stable)

```
yarn install
yarn build
```

**Create first admin user**

```
php bin/console app:create-user <email> <password>
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
```

## Enable Logging

**Setup your Symfony applications**

See package [php-sentinel/bug-catcher-reporter-bundle](https://github.com/php-sentinel/bug-catcher-reporter-bundle)

Have fun!

**Setup non symfony applications**

See package [php-sentinel/bug-catcher-curl-reporter](https://github.com/php-sentinel/bug-catcher-curl-reporter)

## Have Ideas, Feedback or an Issue?

If you have suggestions or questions, please feel free to
open an issue on this repository.


