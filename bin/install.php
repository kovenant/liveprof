<?php

declare(strict_types=1);

/*
 * Creates the "details" table in the database of the LIVE_PROFILER_CONNECTION_URL environment variable.
 */

use Badoo\LiveProfiler\LiveProfiler;

require __DIR__ . '/bootstrap.php';

exit((new LiveProfiler())->createTable() ? 0 : 1);
