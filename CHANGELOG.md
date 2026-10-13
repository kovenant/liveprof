# Live Profiler ChangeLog

## 2.0.0

There are next changes:

- the package is published as `kovenant/liveprof`, a fork of `badoo/liveprof`; the namespace `Badoo\LiveProfiler` is kept
- PHP 8.0 or later is required, support for PHP 5.4-7.4 and HHVM is dropped
- doctrine/dbal 2.13, 3 and 4 are supported (the connection url is parsed with the DSN parser of dbal 3.6+ and 4)
- psr/log 1, 2 and 3 are supported
- type declarations and `declare(strict_types=1)` in all files, properties and variables in camelCase
- `DataPackerInterface` declares the types `pack(array $data): string` and `unpack(string $data): array`; `DataPacker::pack()` throws `JsonException` for data which can't be encoded, `unpack()` returns an empty array for invalid JSON
- `LiveProfiler::end()` never throws: any error of the storage is logged and `false` is returned (previously only dbal 2 exceptions were caught)
- `LiveProfiler::createTable()` chooses the SQL script by the database platform, so it works with the mysqli, pgsql and sqlite3 drivers and with connections configured elsewhere; the scripts moved to `bin/install_data/mysql`, `pgsql` and `sqlite` and create the same table as `aggregator:install` of liveprof-ui: `label` is 100 characters instead of 64, `VARCHAR` instead of `CHAR` in PostgreSQL, `DATETIME` instead of `TIMESTAMP ON UPDATE` in MySQL, `perfdata` is text, the engine and the charset are the server defaults (InnoDB and utf8mb4 instead of MyISAM and utf8), the indexes are prefixed with `details_`
- xhprof sampling mode keeps the start time in the profiler instead of the global constant `XHPROF_SAMPLING_BEGIN`, so a profiler can be started more than once in a process
- profiling of a sample of all requests under the label "All" is disabled by default (`setTotalDivider()` enables it, the default was 10000), a divider which is not positive disables the sampling instead of throwing `ValueError`
- `SimpleProfiler::enable()` starts a new profile instead of adding to the data of the previous run, `disable()` always stops the profiler, the timers use monotonic time
- `SimpleProfiler` collects the CPU time (`cpu`, user and system time from `getrusage()`) in addition to the wall time, the memory usage and the calls, so the CPU metric of liveprof-ui is filled for its profiles too
- `Logger` accepts the log file in the constructor, `setLogFile()` returns the logger, the context is appended as plain JSON
- `LiveProfiler::useUprofiler()` and the uprofiler and HHVM docker files are removed: the extension does not exist for PHP 7 and later
- only the database storage is kept: the file storage (`MODE_FILES`, `setPath()`, `LIVE_PROFILER_PATH`), the liveprof.org API (`MODE_API`, `setApiKey()`, `LIVE_PROFILER_API_URL`), `setMode()` and the corresponding examples are removed
- `ext-zlib` and `ext-curl` are not required any more
- Travis CI and Scrutinizer configs are replaced with GitHub Actions running the tests on PHP 8.0-8.5 with doctrine/dbal 2.13, 3 and 4; php-cs-fixer and PHPStan configs are added
- the example scripts `bin/example.php` and `bin/example_samples.php` share `bin/bootstrap.php` and `bin/example_workload.php`, `bin/install.php` exits with code 1 when the table is not created

## 1.3.4

There are next changes:

- added a memory  usage for SimpleProfiler
- bug fixes

## 1.3.3

There are next changes:

- added SimpleProfiler to use liveprof without any extension

## 1.3.2

There are next changes:

- changed version of doctrine/dbal
- added php 7.3 support

## 1.3.1

There are next changes:

- added example of sending profiling results to api

## 1.3.0

There are next changes:

- added sending profile to api

## 1.2.0

There are next changes:

- added xhprof_sample support

## 1.1.0

There are next changes:

- added possibility to save profiles in the files
