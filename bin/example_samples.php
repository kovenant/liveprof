<?php

declare(strict_types=1);

/*
 * Profiles the test code with xhprof in the sampling mode and saves the profile into an SQLite database in memory.
 */

use Badoo\LiveProfiler\LiveProfiler;

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/example_workload.php';

if (!function_exists('xhprof_sample_enable')) {
    fwrite(\STDERR, "The xhprof extension is required for the sampling mode\n");
    exit(1);
}

$profiler = new LiveProfiler('sqlite:///:memory:');
$profiler->setDivider(1);
$profiler->createTable();
$profiler->useXhprofSample();

// Run this method before the profiled code
$profiler->start();

testCode(1);
usleep(100000);
getSlower(1);

// Run this method after the profiled code, otherwise it is called on shutdown
$result = $profiler->end();
print_r($profiler->getLastProfileData());

echo $result ? "Profiling successfully finished\n" : "Error in profiling\n";
