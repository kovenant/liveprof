<?php

declare(strict_types=1);

/*
 * Profiles the test code with the detected profiler and saves the profile into an SQLite database in memory.
 */

use Badoo\LiveProfiler\LiveProfiler;

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/example_workload.php';

$profiler = new LiveProfiler('sqlite:///:memory:');
$profiler->setDivider(1);
$profiler->createTable();

// Run this method before the profiled code
$profiler->start();

testCode(1);

// Run this method after the profiled code, otherwise it is called on shutdown
$result = $profiler->end();
print_r($profiler->getLastProfileData());

echo $result ? "Profiling successfully finished\n" : "Error in profiling\n";
