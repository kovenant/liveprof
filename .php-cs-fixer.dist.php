<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/bin']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP8x0Migration' => true,
        '@PHP8x0Migration:risky' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'cast_spaces' => ['space' => 'none'],
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => true,
        'phpdoc_align' => ['align' => 'left'],
        'single_line_empty_body' => true,
    ])
    ->setFinder($finder);
