# Catch every bug in your Symfony application

![screenshot](docs/bug_catcher_01.png)

## Setup

**Download the code**

```
git clone git@github.com:php-sentinel/bug-catcher.git
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

**Start the built-in web server**

You can use Nginx or Apache, but the built-in web server works
great:

```
php bin/console server:run
```

Now check out the site at `http://localhost:8000`

**Setup your applications**

In your application where your want catch errors require package ```php-sentinel/bug-catcher-reporter-bundle```
and follow instruction in [php-sentinel/bug-catcher-reporter-bundle](https://github.com/php-sentinel/bug-catcher-reporter-bundle)

Have fun!

## Have Ideas, Feedback or an Issue?

If you have suggestions or questions, please feel free to
open an issue on this repository or comment on the course
itself. We're watching both :).

## Thanks!

And as always, thanks so much for your support and letting
us do what we love!
