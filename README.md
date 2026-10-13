Live Profiler
================

![logo](images/liveprof_logo.png "logo")

Live Profiler is a system-wide performance monitoring system in use at Badoo that is built on top of [XHProf](https://pecl.php.net/package/xhprof) or its fork [Tideways XHProf](https://github.com/tideways/php-xhprof-extension).
Live Profiler continually gathers function-level profiler data from production tier by running a sample of page requests under XHProf.

[Live profiler UI](https://github.com/kovenant/liveprof-ui) then aggregates the profile data corresponding to individual requests by various dimensions such a time, page type, and can help answer a variety of questions such as:
What is the function-level profile for a specific page?
How expensive is function "foo" across all pages, or on a specific page?
What functions regressed most in the last day/week/month?
What is the historical trend for execution time of a page/function? and so on.

This repository is a fork of [badoo/liveprof](https://github.com/badoo/liveprof) published as the Composer package `kovenant/liveprof`. The fork requires PHP 8, supports doctrine/dbal 2.13, 3 and 4 and stores the profiles in a database only; the namespace `Badoo\LiveProfiler` and the public API are kept, so it replaces the original package without code changes (see [Upgrading from 1.x](#upgrading-from-1x)). The matching fork of the UI is [kovenant/liveprof-ui](https://github.com/kovenant/liveprof-ui).

[![CI](https://github.com/kovenant/liveprof/actions/workflows/ci.yml/badge.svg)](https://github.com/kovenant/liveprof/actions/workflows/ci.yml)
[![GitHub license](https://img.shields.io/github/license/kovenant/liveprof.svg)](LICENSE)

System Requirements
===================

* PHP 8.0 or later with the `json` extension.
* [doctrine/dbal](https://www.doctrine-project.org/projects/dbal.html) 2.13, 3 or 4 with a driver for the database which stores the profiles. The installer creates the table in MySQL, PostgreSQL and SQLite.
* One of the [XHProf](https://pecl.php.net/package/xhprof) or [Tideways XHProf](https://github.com/tideways/php-xhprof-extension) extensions to profile and collect the data.
  Without an extension the built-in `SimpleProfiler` measures the wall time, the CPU time and the memory usage of the code sections marked with its timers.
  You can also use any other profiler which returns data in the following format:
  ```php
  $data = [
      'main()' => ['ct' => 1, 'wt' => 1500, 'cpu' => 1200, 'mu' => 2048],
      'main()==>Foo::bar' => ['ct' => 3, 'wt' => 900, 'cpu' => 700, 'mu' => 1024],
  ];
  ```

Installation
============

1. You can install Live Profiler via [Composer](https://getcomposer.org/):

```bash
composer require kovenant/liveprof
```

2. Prepare the database. You can use any driver described [here](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html) or implement a custom one. Run the script which creates the "details" table (the SQL scripts for MySQL, PostgreSQL and SQLite are in `bin/install_data`). The table is the same as the one created by `aggregator:install` of [Live profiler UI](https://github.com/kovenant/liveprof-ui), so either tool can create it:

```bash
LIVE_PROFILER_CONNECTION_URL=mysql://db_user:db_password@db_mysql:3306/Profiler?charset=utf8 php vendor/kovenant/liveprof/bin/install.php
```

3. Init the profiler before the working code in the project entry point (usually public/index.php).

Usage
=====

There is an example of usage of the profiler with default parameters:
```php
<?php
include 'vendor/autoload.php';

\Badoo\LiveProfiler\LiveProfiler::getInstance()->start();
// Code is here
```

There is an example how to use Live Profiler without any extension. The built-in profiler collects the data in the XHProf format, so [Live profiler UI](https://github.com/kovenant/liveprof-ui) shows it like the profiles of XHProf:
```php
<?php
include 'vendor/autoload.php';

\Badoo\LiveProfiler\LiveProfiler::getInstance()
     ->useSimpleProfiler() // Use the built-in profiler instead of XHProf or its forks
     ->setApp('Demo') // Some unique app name
     ->start();

// Code is here
// start a timer before each important method
\Badoo\LiveProfiler\SimpleProfiler::getInstance()->startTimer(__METHOD__); // any string can be used as a timer tag
// stop the timer before the end of the method
\Badoo\LiveProfiler\SimpleProfiler::getInstance()->endTimer(__METHOD__); // any string can be used as a timer tag
```

There is a full list of methods you can use to change options:
```php
<?php

// Start profiling
\Badoo\LiveProfiler\LiveProfiler::getInstance()
    ->setConnectionString('mysql://db_user:db_password@db_mysql:3306/Profiler?charset=utf8') // optional, you can also set the connection url in the environment variable LIVE_PROFILER_CONNECTION_URL
    ->setConnection($Connection) // optional, a custom instance of \Doctrine\DBAL\Connection if you can't use the connection url
    ->setApp('Site1') // optional, current app name to use one profiler in several apps, "Default" by default
    ->setLabel('users') // optional, the request name, by default the url path or script name in cli
    ->setDivider(700) // optional, profiling starts for 1 of 700 requests with the same app and label, 1000 by default
    ->setTotalDivider(7000) // optional, profiling starts for 1 of 7000 requests with the forced label "All", disabled (0) by default
    ->setLogger($Logger) // optional, a custom logger implemented \Psr\Log\LoggerInterface
    ->setDataPacker($DataPacker) // optional, a class implemented \Badoo\LiveProfiler\DataPackerInterface to convert array into string
    ->setStartCallback($profiler_start_callback) // optional, set it if you use custom profiler
    ->setEndCallback($profiler_end_callback) // optional, set it if you use custom profiler
    ->useXhprof() // optional, force use xhprof as profiler
    ->useTidyWays() // optional, force use Tideways XHProf as profiler
    ->useSimpleProfiler() // optional, force use the built-in profiler
    ->useXhprofSample() // optional, force use xhprof in sampling mode
    ->start();
```

If you want to change the Label during running (for instance, after you got some information in the router or controller) you can call:
```php
<?php

$number = random_int(0, 100);
$current_label = \Badoo\LiveProfiler\LiveProfiler::getInstance()->getLabel();
\Badoo\LiveProfiler\LiveProfiler::getInstance()->setLabel($current_label . $number);
```

if you don't want to save profiling result you can reset it anytime:
```php
<?php

\Badoo\LiveProfiler\LiveProfiler::getInstance()->reset();
```

After script ends it will call `\Badoo\LiveProfiler\LiveProfiler::getInstance()->end();` on shutdown, but you can call it explicitly after working code.

Errors of the profiler never interrupt the application: `end()` returns `false` and the problem is written by the logger, by default into `live.profiler.log` in the package directory.

Environment Variables
=====================

`LIVE_PROFILER_CONNECTION_URL`: [url](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#connecting-using-a-url) for the database connection

Work flow
=========

Live profiler allows to run profiling with custom frequency (for instance 1 of 1000 requests) grouped by app name ('Default' by default) and custom label (by default it's the url path or script name).

It's important to calculate **the request divider** properly to have enough data for aggregation. You should divide daily request count to have approximately 1 profile per minute.
For instance, if you have 1M requests a day for the page /users the divider should be 1000000/(60*24) = 694, so divider = 700 is enough.

Optionally you can profile a sample of all requests under the label "All" to control the whole system health; it is disabled by default and enabled by `setTotalDivider()`. Calculate **the total divider** the same way: use the count of all daily requests. For instance, if you have 10M requests a day - total_divider=10000000/(60*24) = 6940, so total_divider = 7000 is enough.

The profiler automatically detects which profiler extension you have (xhprof or tideways_xhprof) and falls back to the built-in `SimpleProfiler`. You have to set profiler callbacks if you use another profiler.

You can run the test script in the docker container with the **xhprof** extension and look at the example of the server configuration in Dockerfile:
```bash
docker build -t kovenant/liveprof .
docker run --rm kovenant/liveprof
```

or you can build a docker container with the **tideways_xhprof** extension:
```bash
docker build -f DockerfileTidyWays -t kovenant/liveprof-tideways .
docker run --rm kovenant/liveprof-tideways
```

The example `bin/example_samples.php` uses xhprof in the sampling mode and runs in the same container:
```bash
docker run --rm kovenant/liveprof php bin/example_samples.php
```

Steps to install the tideways extension:
```bash
git clone https://github.com/tideways/php-xhprof-extension.git
cd php-xhprof-extension
phpize
./configure
make
make install
echo "extension=tideways_xhprof.so" >> /usr/local/etc/php/conf.d/20-tideways_xhprof.ini
```

Upgrading from 1.x
==================

* PHP 8.0 or later is required; doctrine/dbal 2.13, 3 and 4 and psr/log 1, 2 and 3 are supported.
* Profiles are saved only in the database: `MODE_FILES`, `MODE_API`, `setMode()`, `setPath()`, `setApiKey()` and the `LIVE_PROFILER_PATH` and `LIVE_PROFILER_API_URL` variables are removed, the constructor takes the connection url only.
* `DataPackerInterface` declares the parameter and return types, a custom data packer has to declare them too.
* `useUprofiler()` is removed: the uprofiler extension does not exist for PHP 7 and later.
* `createTable()` (and `bin/install.php`) selects the SQL script by the database platform; the scripts live in `bin/install_data/mysql`, `pgsql` and `sqlite` and define the table like liveprof-ui does (`label` of 100 characters, `DATETIME`, text `perfdata`, indexes `details_*`). Tables created by 1.x keep working, but labels longer than 64 characters are not saved into them. To align an existing table run on MySQL:
  ```sql
  ALTER TABLE details
    MODIFY label VARCHAR(100) DEFAULT NULL,
    MODIFY timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFY perfdata MEDIUMTEXT;
  ```
  and on PostgreSQL (the cast drops the padding of the `CHAR` columns):
  ```sql
  ALTER TABLE details
    ALTER COLUMN app TYPE VARCHAR(32),
    ALTER COLUMN label TYPE VARCHAR(100);
  ```
  SQLite ignores the column lengths, so nothing is needed there.
* `SimpleProfiler::enable()` starts a new profile instead of adding to the data of the previous run.

See [CHANGELOG.md](CHANGELOG.md) for the full list of changes.

Development
===========

Install the dependencies and run the checks (php-cs-fixer in the dry-run mode, PHPStan and PHPUnit). The database tests use SQLite in memory and need the `pdo_sqlite` extension:
```bash
composer install
composer test
```

The checks are also available separately: `composer cs-view`, `composer cs-fix`, `composer phpstan`, `composer phpunit` and `composer coverage` (needs Xdebug).

License
=======

This project is licensed under the MIT open source license.
