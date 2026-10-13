<?php

declare(strict_types=1);

/*
 * Symbols of the tideways_xhprof extension for PHPStan; the file is only scanned, never loaded.
 */

const TIDEWAYS_XHPROF_FLAGS_NO_BUILTINS = 1;
const TIDEWAYS_XHPROF_FLAGS_CPU = 2;
const TIDEWAYS_XHPROF_FLAGS_MEMORY = 4;

function tideways_xhprof_enable(int $flags = 0): void
{
}

/**
 * @return array<string, array<string, int>>
 */
function tideways_xhprof_disable(): array
{
    return [];
}
