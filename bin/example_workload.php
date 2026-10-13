<?php

declare(strict_types=1);

/*
 * Functions profiled by the example scripts: they only burn CPU time.
 */

function testCode(int $level = 2): void
{
    getSlower($level);
    getFaster($level);
}

// @phpstan-ignore void.pure (the function only burns CPU time)
function getSlower(int $level): void
{
    for ($i = 0; $i < $level; ++$i) {
        for ($j = 0; $j < 1000000; ++$j) {
            $result = $j * (1000000 - $j);
        }
    }
}

// @phpstan-ignore void.pure (the function only burns CPU time)
function getFaster(int $level): void
{
    for ($i = 0; $i < (10 - $level); ++$i) {
        for ($j = 0; $j < 1000000; ++$j) {
            $result = $i * (1000000 - $i);
        }
    }
}
