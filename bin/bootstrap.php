<?php

declare(strict_types=1);

/*
 * Loads the Composer autoloader of the package itself or of the project the package is installed into.
 */

foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../../../vendor/autoload.php'] as $autoload) {
    if (file_exists($autoload)) {
        require_once $autoload;

        break;
    }
}
